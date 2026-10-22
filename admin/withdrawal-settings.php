<?php
$PAGE_TITLE = 'Withdrawal settings';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pdo->prepare('UPDATE withdrawal_settings SET min_amount=?, basis=?, admin_charge_pct=?, tds_pct=?, auto_enabled=? WHERE id=1')
        ->execute([
            (float)$_POST['min_amount'], $_POST['basis'],
            (float)$_POST['admin_charge_pct'], (float)$_POST['tds_pct'],
            isset($_POST['auto_enabled']) ? 1 : 0,
        ]);
    log_activity('withdrawal_settings.update');
    $msg = 'Withdrawal settings saved.';
}
$s = $pdo->query('SELECT * FROM withdrawal_settings WHERE id=1')->fetch();
?>
<h1>Withdrawal settings</h1><p class="sub">Set eligibility and deductions for future withdrawal requests.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:16px"><h3>Eligibility &amp; charges</h3>
<form method="post"><?= csrf_field() ?>
<div class="fr"><div class="field"><label>Minimum gross amount (₹)</label><input type="number" step="0.01" name="min_amount" value="<?= htmlspecialchars($s['min_amount']) ?>"></div>
<div class="field"><label>Withdrawal basis</label><select name="basis">
<?php foreach (['daily'=>"Each completed day's earnings",'weekly'=>'Weekly balance','monthly'=>'Monthly balance'] as $k=>$l): ?>
<option value="<?= $k ?>" <?= $s['basis']===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></div></div>
<div class="fr"><div class="field"><label>Admin charge (%)</label><input type="number" step="0.01" name="admin_charge_pct" value="<?= htmlspecialchars($s['admin_charge_pct']) ?>"></div>
<div class="field"><label>TDS (%)</label><input type="number" step="0.01" name="tds_pct" value="<?= htmlspecialchars($s['tds_pct']) ?>"></div></div>
<label style="display:flex;gap:8px;align-items:center;margin:6px 0 14px"><input type="checkbox" name="auto_enabled" <?= $s['auto_enabled']?'checked':'' ?>>Enable automatic withdrawal requests</label>
<div class="alert ok">Both deductions use gross earnings. Approved KYC is required. Blocked accounts are skipped. Existing queued and paid amounts stay unchanged.</div>
<button class="btn">Save withdrawal settings</button>
</form></div>
<?php require __DIR__ . '/_bottom.php'; ?>
