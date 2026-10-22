<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_member();
$pdo = db();
$memberId = current_member_id();
$meStmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
$meStmt->execute([$memberId]);
$ME = $meStmt->fetch();
if (!$ME) { logout(); header('Location: /login.php'); exit; }

$PAGE_TITLE = $PAGE_TITLE ?? 'Dashboard';
$current = basename($_SERVER['SCRIPT_NAME']);
function mnav(string $href, string $icon, string $label, string $current, ?int $pill = null): void
{
    $on = $current === $href ? ' on' : '';
    $pillHtml = $pill ? '<span class="pill">' . (int)$pill . '</span>' : '';
    echo '<a class="nav' . $on . '" href="' . htmlspecialchars($href) . '"><i>' . $icon . '</i>' . htmlspecialchars($label) . $pillHtml . '</a>';
}
$kycAlert = $ME['kyc_status'] !== 'approved' ? 1 : null;
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>GSAA Global – <?= htmlspecialchars($PAGE_TITLE) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
</head><body>
<aside>
<div class="brand"><div class="logo">G</div><div><b>GSAA</b><small><?= htmlspecialchars($ME['name']) ?></small></div></div>
<?php
mnav('index.php', '▦', 'Dashboard', $current);
mnav('team.php', '⑂', 'My team', $current);
mnav('services.php', '⚙', 'Services', $current);
mnav('income.php', '↗', 'My income', $current);
mnav('kyc.php', '✓', 'KYC', $current, $kycAlert);
mnav('withdraw.php', '⇄', 'Withdraw', $current);
mnav('profile.php', '◎', 'Profile', $current);
?>
<a class="sob" href="/logout.php"><i style="font-style:normal;width:18px;text-align:center">⏻</i>Sign out</a>
</aside>
<main>
<header><span class="crumb"><span class="ws">Workspace / </span><b style="color:var(--ink)"><?= htmlspecialchars($PAGE_TITLE) ?></b></span><span class="sp"></span>
<span style="font-size:12.5px;color:var(--mut)"><?= htmlspecialchars($ME['member_code']) ?></span>
</header>
<div class="wrap">
