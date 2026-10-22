<?php
require_once __DIR__ . '/lib/bootstrap.php';

if (($_SESSION['role'] ?? null) === 'admin') {
    header('Location: /admin/index.php');
    exit;
}
if (($_SESSION['role'] ?? null) === 'member') {
    header('Location: /member/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = trim($_POST['id'] ?? '');
    $pw = (string)($_POST['password'] ?? '');
    if ($id === '' || $pw === '') {
        $error = 'Enter your ID and password to continue.';
    } else {
        $result = attempt_login($id, $pw);
        if ($result) {
            header('Location: ' . ($result['role'] === 'admin' ? '/admin/index.php' : '/member/index.php'));
            exit;
        }
        $error = 'ID or password is incorrect, or too many attempts — please try again in a few minutes.';
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GSAA Global – Sign in</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<style>
body{min-height:100vh;background:linear-gradient(135deg,var(--side),#4a1216 60%,var(--em));display:flex;align-items:center;justify-content:center;padding:24px}
.box{background:var(--card);border-radius:22px;padding:34px 32px;width:100%;max-width:400px;box-shadow:0 20px 60px #0007}
.brand{display:flex;gap:10px;align-items:center;margin-bottom:22px}
.logo{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--em2),var(--gold));display:grid;place-items:center;font:800 20px 'Bricolage Grotesque',sans-serif;color:#fff}
.brand b{font-size:18px}.brand small{display:block;color:var(--mut);font-size:11.5px}
h1{font-size:22px;margin-bottom:4px}.sub{color:var(--mut);margin-bottom:20px;font-size:13.5px}
.err{background:#fde9dd;color:#c2410c;border-radius:10px;padding:9px 12px;font-size:13px;margin-bottom:12px}
</style></head>
<body>
<div class="box">
  <div class="brand"><div class="logo">G</div><div><b><?= htmlspecialchars(SITE_NAME) ?></b><small>Unified sign in</small></div></div>
  <h1>Welcome back</h1>
  <p class="sub">Sign in with your ID and password. We'll open your dashboard automatically based on your role.</p>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label>Admin ID / Member ID / mobile number</label><input name="id" autocomplete="username" required></div>
    <div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn" style="width:100%;margin-top:6px">Sign in →</button>
  </form>
</div>
</body></html>
