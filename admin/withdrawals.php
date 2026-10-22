<?php
$PAGE_TITLE = 'Withdrawals';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'process') {
        $n = process_eligible_withdrawals();
        log_activity('withdrawals.process', null, "$n requests created");
        $msg = $n . ' withdrawal request(s) created for eligible members.';
    } elseif ($action === 'mark_paid') {
        $id = (int)$_POST['id'];
        $ref = trim($_POST['bank_ref'] ?? '');
        $pdo->prepare("UPDATE withdrawals SET status='paid', bank_ref=?, processed_by=?, processed_at=? WHERE id=?")
            ->execute([$ref, current_admin_id(), date('Y-m-d H:i:s'), $id]);
        log_activity('withdrawal.paid', 'Withdrawal #' . $id, $ref);
        $msg = 'Marked as paid.';
    }
}

$settings = $pdo->query('SELECT * FROM withdrawal_settings WHERE id=1')->fetch();
$totals = $pdo->query("SELECT COALESCE(SUM(gross),0) g, COALESCE(SUM(admin_charge),0) a, COALESCE(SUM(tds),0) t, COALESCE(SUM(net),0) n FROM withdrawals")->fetch();

$balRows = $pdo->query(
    "SELECT m.id, m.member_code, m.name, m.kyc_status, m.status, COALESCE(SUM(l.amount),0) bal
     FROM members m LEFT JOIN income_ledger l ON l.receiver_id = m.id AND l.status='available'
     GROUP BY m.id HAVING bal > 0 ORDER BY bal DESC"
)->fetchAll();

$reqRows = $pdo->query(
    "SELECT w.*, m.member_code, m.name FROM withdrawals w JOIN members m ON m.id = w.member_id ORDER BY w.id DESC LIMIT 100"
)->fetchAll();
?>
<h1>Withdrawals</h1><p class="sub">Daily automatic requests and recorded bank payments.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="alert ok">Requests reserve earnings automatically. This app does not send bank transfers — complete payment through your bank, then mark it as paid here.</div>
<div class="grid g4" style="margin-bottom:16px">
<div class="card stat"><small>Gross withdrawals</small><div class="num"><?= rupee($totals['g']) ?></div><em>All time</em></div>
<div class="card stat"><small>Admin charges</small><div class="num"><?= rupee($totals['a']) ?></div><em>All time</em></div>
<div class="card stat"><small>TDS deductions</small><div class="num"><?= rupee($totals['t']) ?></div><em>All time</em></div>
<div class="card stat gold"><small>Net payable</small><div class="num"><?= rupee($totals['n']) ?></div><em>All time</em></div>
</div>
<div class="card" style="margin-bottom:16px">
<form method="post"><input type="hidden" name="action" value="process"><?= csrf_field() ?>
<button class="btn g">Process eligible completed days now</button></form>
<p class="s" style="margin-top:8px">Minimum <?= rupee($settings['min_amount']) ?> · admin <?= $settings['admin_charge_pct'] ?>% · TDS <?= $settings['tds_pct'] ?>%. <a class="lnk" href="withdrawal-settings.php">Change settings</a></p>
</div>
<div class="card" style="margin-bottom:16px"><h3>Available balances</h3><div class="s">Unwithdrawn income by member right now.</div>
<div class="tw"><table><thead><tr><th>Member</th><th>Available</th><th>KYC</th><th>Status</th></tr></thead><tbody>
<?php foreach ($balRows as $b): ?>
<tr><td><b><?= htmlspecialchars($b['name']) ?></b><br><span class="s"><?= htmlspecialchars($b['member_code']) ?></span></td>
<td><?= rupee($b['bal']) ?></td>
<td><span class="tag <?= $b['kyc_status']==='approved'?'ok':'' ?>"><?= ucfirst($b['kyc_status']) ?></span></td>
<td><span class="tag <?= $b['status']==='active'?'ok':'' ?>"><?= ucfirst($b['status']) ?></span></td></tr>
<?php endforeach; if (!$balRows): ?><tr><td colspan="4" class="muted-cell">No available income right now.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="card"><h3>Requests</h3>
<div class="tw"><table><thead><tr><th>ID / cycle</th><th>Member</th><th>Gross</th><th>Admin</th><th>TDS</th><th>Net</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($reqRows as $w): ?>
<tr><td>#<?= $w['id'] ?><br><span class="s"><?= htmlspecialchars($w['cycle_date']) ?></span></td>
<td><b><?= htmlspecialchars($w['name']) ?></b><br><span class="s"><?= htmlspecialchars($w['member_code']) ?></span></td>
<td><?= rupee($w['gross']) ?></td><td><?= rupee($w['admin_charge']) ?></td><td><?= rupee($w['tds']) ?></td><td><?= rupee($w['net']) ?></td>
<td><span class="tag <?= $w['status']==='paid'?'ok':'gold' ?>"><?= ucfirst($w['status']) ?></span></td>
<td><?php if ($w['status']==='pending'): ?>
<form method="post" style="display:flex;gap:4px"><input type="hidden" name="id" value="<?= $w['id'] ?>"><input type="hidden" name="action" value="mark_paid"><?= csrf_field() ?>
<input name="bank_ref" placeholder="Bank ref" style="width:110px;padding:5px 8px;font-size:12.5px"><button class="btn" style="padding:5px 10px;font-size:12.5px">Mark paid</button></form>
<?php else: ?><span class="s"><?= htmlspecialchars($w['bank_ref'] ?? '') ?></span><?php endif; ?></td></tr>
<?php endforeach; if (!$reqRows): ?><tr><td colspan="8" class="muted-cell">No withdrawal requests yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
