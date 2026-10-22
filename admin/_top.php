<?php
/**
 * Shared admin layout header. Each admin page sets $PAGE_TITLE and
 * $PAGE_CRUMB before requiring this file, then requires _bottom.php at
 * the end.
 */
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$PAGE_TITLE = $PAGE_TITLE ?? 'Overview';
$PAGE_CRUMB = $PAGE_CRUMB ?? $PAGE_TITLE;
$current = basename($_SERVER['SCRIPT_NAME']);

$kycPending = (int)db()->query("SELECT COUNT(*) c FROM members WHERE kyc_status = 'pending'")->fetch()['c'];
$reviewPending = (int)db()->query("SELECT COUNT(*) c FROM service_submissions WHERE status = 'pending'")->fetch()['c'];

function nav_link(string $href, string $icon, string $label, string $current, ?int $pill = null): void
{
    $on = $current === $href ? ' on' : '';
    $pillHtml = $pill ? '<span class="pill">' . (int)$pill . '</span>' : '';
    echo '<a class="nav' . $on . '" href="' . htmlspecialchars($href) . '"><i>' . $icon . '</i>' . htmlspecialchars($label) . $pillHtml . '</a>';
}
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
<div class="brand"><div class="logo">G</div><div><b>GSAA</b><small>Global admin</small></div></div>
<?php
nav_link('index.php', '▦', 'Overview', $current);
nav_link('members.php', '☺', 'Members', $current);
nav_link('tree.php', '⑂', 'Member tree', $current);
nav_link('kyc.php', '✓', 'KYC reviews', $current, $kycPending ?: null);
nav_link('payments.php', '₹', 'Payments', $current);
nav_link('qr-payments.php', '▣', 'QR payments', $current);
echo '<div class="sep">More modules</div>';
nav_link('income-reports.php', '↗', 'Income reports', $current);
nav_link('services.php', '⚙', 'Services', $current);
nav_link('service-reviews.php', '★', 'Service reviews', $current, $reviewPending ?: null);
nav_link('withdrawals.php', '⇄', 'Withdrawals', $current);
nav_link('withdrawal-settings.php', '⚒', 'Withdrawal settings', $current);
nav_link('income-setup.php', '◎', 'Income setup', $current);
nav_link('activity-log.php', '≡', 'Activity log', $current);
?>
<a class="sob" href="/logout.php"><i style="font-style:normal;width:18px;text-align:center">⏻</i>Sign out</a>
</aside>
<main>
<header><span class="crumb"><span class="ws">Workspace / </span><b style="color:var(--ink)"><?= htmlspecialchars($PAGE_CRUMB) ?></b></span><span class="sp"></span>
<span style="font-size:12.5px;color:var(--mut)"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></span>
</header>
<div class="wrap">
