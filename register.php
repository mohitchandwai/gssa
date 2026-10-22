<?php
require_once __DIR__ . '/lib/bootstrap.php';

if (($_SESSION['role'] ?? null) === 'member') { header('Location: /member/index.php'); exit; }
if (($_SESSION['role'] ?? null) === 'admin') { header('Location: /admin/index.php'); exit; }

$pdo = db();
$refCode = trim($_GET['ref'] ?? $_POST['ref'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $refCode = trim($_POST['ref'] ?? '');

    if (!$name || !preg_match('/^[0-9]{10}$/', $phone) || strlen($password) < 6) {
        $error = 'Enter your name, a valid 10-digit mobile number, and a password of at least 6 characters.';
    } else {
        $dup = $pdo->prepare('SELECT 1 FROM members WHERE phone = ?');
        $dup->execute([$phone]);
        if ($dup->fetch()) {
            $error = 'This mobile number is already registered. Try signing in instead.';
        } else {
            $sponsorId = null;
            if ($refCode !== '') {
                $s = $pdo->prepare('SELECT id FROM members WHERE member_code = ?');
                $s->execute([$refCode]);
                $sr = $s->fetch();
                $sponsorId = $sr ? (int)$sr['id'] : null;
            }
            $code = new_member_code();
            $ins = $pdo->prepare(
                'INSERT INTO members (member_code, name, phone, password_hash, sponsor_id, package_code, status, kyc_status)
                 VALUES (?,?,?,?,?,"none","inactive","pending")'
            );
            $ins->execute([$code, $name, $phone, password_hash($password, PASSWORD_DEFAULT), $sponsorId]);
            $newId = (int)$pdo->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['role'] = 'member';
            $_SESSION['member_id'] = $newId;
            $_SESSION['name'] = $name;
            log_activity('member.register', 'Member #' . $code);
            header('Location: /member/index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GSAA Global – Join</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<style>
body{min-height:100vh;background:linear-gradient(135deg,var(--side),#4a1216 60%,var(--em));display:flex;align-items:center;justify-content:center;padding:24px}
.box{background:var(--card);border-radius:22px;padding:34px 32px;width:100%;max-width:420px;box-shadow:0 20px 60px #0007}
.brand{display:flex;gap:10px;align-items:center;margin-bottom:22px}
.logo{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--em2),var(--gold));display:grid;place-items:center;font:800 20px 'Bricolage Grotesque',sans-serif;color:#fff}
.brand b{font-size:18px}.brand small{display:block;color:var(--mut);font-size:11.5px}
h1{font-size:22px;margin-bottom:4px}.sub{color:var(--mut);margin-bottom:20px;font-size:13.5px}
.err{background:#fde9dd;color:#c2410c;border-radius:10px;padding:9px 12px;font-size:13px;margin-bottom:12px}
</style></head>
<body>
<div class="box">
<div class="brand"><div class="logo">G</div><div><b>GSAA</b><small>Join the network</small></div></div>
<h1>Create your account</h1>
<p class="sub"><?= $refCode ? 'Referred by ' . htmlspecialchars($refCode) . '.' : 'No referrer? You can still join and activate directly.' ?></p>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="ref" value="<?= htmlspecialchars($refCode) ?>">
<div class="field"><label>Full name</label><input name="name" required></div>
<div class="field"><label>Mobile number</label><input name="phone" pattern="[0-9]{10}" maxlength="10" placeholder="10 digits" required></div>
<div class="field"><label>Password</label><input type="password" name="password" minlength="6" required></div>
<button class="btn" style="width:100%;margin-top:6px">Create account →</button>
</form>
<p class="sub" style="margin-top:16px">Already have an account? <a class="lnk" href="/login.php">Sign in</a></p>
</div>
</body></html>
