<?php
/**
 * Session bootstrap, login/logout, role guards, login throttling.
 * Include config.php and lib/db.php before this file.
 */

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => $secure,     // true once you're on HTTPS (required on production)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('gsaa_sess');
    session_start();

    // Basic idle timeout
    if (!empty($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > SESSION_LIFETIME) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['last_seen'] = time();
}

/** Throttle brute-force login attempts per identifier (username/phone + IP). */
function login_throttle_check(string $identifier): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $identifier . '|' . $ip;
    $stmt = db()->prepare(
        'SELECT COUNT(*) c FROM login_throttle WHERE identifier = ? AND attempt_at > ?'
    );
    $since = date('Y-m-d H:i:s', time() - LOGIN_THROTTLE_WINDOW);
    $stmt->execute([$key, $since]);
    $row = $stmt->fetch();
    return ((int)$row['c']) < LOGIN_THROTTLE_MAX;
}

function login_throttle_record(string $identifier): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $identifier . '|' . $ip;
    $stmt = db()->prepare('INSERT INTO login_throttle (identifier) VALUES (?)');
    $stmt->execute([$key]);
}

function login_throttle_clear(string $identifier): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $identifier . '|' . $ip;
    $stmt = db()->prepare('DELETE FROM login_throttle WHERE identifier = ?');
    $stmt->execute([$key]);
}

/**
 * Try to sign a user in by ID + password. Looks in admin_users first, then
 * members, so one login form can route to either dashboard.
 * Returns ['role'=>'admin'|'member', ...] on success, or null on failure.
 */
function attempt_login(string $id, string $password): ?array
{
    if (!login_throttle_check($id)) {
        return null; // too many attempts — caller should show a generic "try later" message
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        login_throttle_clear($id);
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin';
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['name'] = $admin['name'];
        return ['role' => 'admin', 'id' => (int)$admin['id'], 'name' => $admin['name']];
    }

    $stmt = db()->prepare('SELECT * FROM members WHERE member_code = ? OR phone = ? LIMIT 1');
    $stmt->execute([$id, $id]);
    $member = $stmt->fetch();
    if ($member && password_verify($password, $member['password_hash'])) {
        if ($member['status'] === 'blocked') {
            return null;
        }
        login_throttle_clear($id);
        session_regenerate_id(true);
        $_SESSION['role'] = 'member';
        $_SESSION['member_id'] = (int)$member['id'];
        $_SESSION['name'] = $member['name'];
        return ['role' => 'member', 'id' => (int)$member['id'], 'name' => $member['name']];
    }

    login_throttle_record($id);
    return null;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_admin_id(): ?int
{
    return $_SESSION['role'] === 'admin' ? ($_SESSION['admin_id'] ?? null) : null;
}

function current_member_id(): ?int
{
    return ($_SESSION['role'] ?? null) === 'member' ? ($_SESSION['member_id'] ?? null) : null;
}

function require_admin(): void
{
    if (($_SESSION['role'] ?? null) !== 'admin') {
        header('Location: /login.php');
        exit;
    }
}

function require_member(): void
{
    if (($_SESSION['role'] ?? null) !== 'member') {
        header('Location: /login.php');
        exit;
    }
}

function log_activity(string $event, ?string $subject = null, ?string $details = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO activity_log (admin_id, event, subject, details) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([current_admin_id(), $event, $subject, $details]);
}
