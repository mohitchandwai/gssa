<?php
$PAGE_TITLE = 'Profile';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name) {
            $pdo->prepare('UPDATE members SET name=?, email=? WHERE id=?')->execute([$name, $email, $memberId]);
            $ME['name'] = $name; $ME['email'] = $email;
            $msg = 'Profile updated.';
        }
    } elseif ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $ME['password_hash'])) {
            $msg = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $msg = 'New password must be at least 6 characters.';
        } else {
            $pdo->prepare('UPDATE members SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $memberId]);
            $msg = 'Password changed.';
        }
    }
}
?>
<h1>Profile</h1><p class="sub">Your account details.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="grid g2">
<div class="card"><h3>Details</h3>
<form method="post"><input type="hidden" name="action" value="update"><?= csrf_field() ?>
<div class="field"><label>Full name</label><input name="name" value="<?= htmlspecialchars($ME['name']) ?>"></div>
<div class="field"><label>Phone (cannot be changed)</label><input value="<?= htmlspecialchars($ME['phone']) ?>" disabled></div>
<div class="field"><label>Email</label><input name="email" value="<?= htmlspecialchars($ME['email'] ?? '') ?>"></div>
<button class="btn">Save changes</button></form></div>
<div class="card"><h3>Change password</h3>
<form method="post"><input type="hidden" name="action" value="password"><?= csrf_field() ?>
<div class="field"><label>Current password</label><input type="password" name="current" required></div>
<div class="field"><label>New password</label><input type="password" name="new" required minlength="6"></div>
<button class="btn">Update password</button></form></div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
