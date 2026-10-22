<?php
$PAGE_TITLE = 'Withdraw';
require __DIR__ . '/_top.php';
$pdo = db();

$settings = $pdo->query('SELECT * FROM withdrawal_settings WHERE id=1')->fetch();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($ME['kyc_status'] !== 'approved') {
        $msg = 'Your KYC must be approved before you can withdraw.';
    } elseif ($ME['status'] === 'blocked') {
        $msg = 'Your account is blocked. Contact support.';
    } else {
        $stmt = $pdo->prepare("SELECT id, amount FROM income_ledger WHERE receiver_id = ? AND status = 'available'");
        $stmt->execute([$memberId]);
        $rows = $stmt->fetchAll();
        $gross = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
        if ($gross < (float)$settings['min_amount']) {
            $msg = 'Minimum withdrawal amount is ' . rupee($settings['min_amount']) . '. Your available balance is ' . rupee($gross) . '.';
        } else {
            [$admin, $tds, $net] = withdrawal_breakdown($gross, (float)$settings['admin_charge_pct'], (float)$settings['tds_pct']);
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare("INSERT INTO withdrawals (member_id, cycle_date, gross, admin_charge, tds, net, status) VALUES (?,?,?,?,?,?,'pending')");
                $ins->execute([$memberId, date('Y-m-d'), $gross, $admin, $tds, $net]);
                $wid = (int)$pdo->lastInsertId();
                $ids = array_map(fn($r) => (int)$r['id'], $rows);
                $in = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE income_ledger SET status='withdrawn', withdrawal_id=? WHERE id IN ($in)")
                    ->execute(array_merge([$wid], $ids));
                $pdo->commit();
                $msg = 'Withdrawal of ' . rupee($net) . ' (net) requested. The admin will transfer it to your bank and mark it paid.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                $msg = 'Something went wrong — please try again.';
            }
        }
    }
}

$balance = wallet_balance($memberId);
$hist = $pdo->prepare('SELECT * FROM withdrawals WHERE member_id = ? ORDER BY id DESC LIMIT 50');
$hist->execute([$memberId]);
$histRows = $hist->fetchAll();
?>
<h1>Withdraw</h1><p class="sub">Minimum <?= rupee($settings['min_amount']) ?> · admin charge <?= $settings['admin_charge_pct'] ?>% · TDS <?= $settings['tds_pct'] ?>%.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:16px;max-width:360px"><small>Available balance</small><div class="num" style="font-size:30px;font-weight:700;margin:4px 0"><?= rupee($balance) ?></div>
<form method="post"><?= csrf_field() ?><button class="btn" <?= ($balance < (float)$settings['min_amount'] || $ME['kyc_status']!=='approved') ? 'disabled' : '' ?>>Request withdrawal</button></form>
<?php if ($ME['kyc_status'] !== 'approved'): ?><p class="s" style="margin-top:8px">Complete <a class="lnk" href="kyc.php">KYC</a> to enable withdrawals.</p><?php endif; ?>
</div>
<div class="card"><h3>History</h3>
<div class="tw"><table><thead><tr><th>Date</th><th>Gross</th><th>Admin</th><th>TDS</th><th>Net</th><th>Status</th></tr></thead><tbody>
<?php foreach ($histRows as $w): ?>
<tr><td><?= htmlspecialchars($w['cycle_date']) ?></td><td><?= rupee($w['gross']) ?></td><td><?= rupee($w['admin_charge']) ?></td>
<td><?= rupee($w['tds']) ?></td><td><?= rupee($w['net']) ?></td>
<td><span class="tag <?= $w['status']==='paid'?'ok':'gold' ?>"><?= ucfirst($w['status']) ?></span></td></tr>
<?php endforeach; if (!$histRows): ?><tr><td colspan="6" class="muted-cell">No withdrawals yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
