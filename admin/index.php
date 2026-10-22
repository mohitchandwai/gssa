<?php
$PAGE_TITLE = 'Overview';
require __DIR__ . '/_top.php';

$pdo = db();
$totalMembers = (int)$pdo->query('SELECT COUNT(*) c FROM members')->fetch()['c'];
$activeMembers = (int)$pdo->query("SELECT COUNT(*) c FROM members WHERE status='active'")->fetch()['c'];
$notActivated = $totalMembers - $activeMembers;
$premiumMembers = (int)$pdo->query("SELECT COUNT(*) c FROM members WHERE package_code='premium'")->fetch()['c'];
$paymentTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='paid'")->fetch()['s'];
$incomeTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM income_ledger")->fetch()['s'];
$kycPending = (int)$pdo->query("SELECT COUNT(*) c FROM members WHERE kyc_status='pending'")->fetch()['c'];

$dist = $pdo->query(
    "SELECT income_type, COALESCE(SUM(amount),0) s FROM income_ledger GROUP BY income_type"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$distLabels = ['direct'=>'Direct income','parent_level'=>'Parent / Level income','reward'=>'Rewards','boost'=>'Boost income','service'=>'Service income','qr'=>'QR income'];

$latest = $pdo->query(
    "SELECT member_code, name, package_code, status FROM members ORDER BY id DESC LIMIT 6"
)->fetchAll();
?>
<div class="hero"><div><h2>Your network, in focus.</h2><p>Members, payments and income in one live view. Only KYC-approved members enter the hierarchy.</p></div>
<div class="k"><div><b><?= $totalMembers ?></b>in the tree</div><div><b><?= $kycPending ?></b>awaiting KYC</div></div></div>
<div class="grid g4" style="margin-bottom:16px">
<div class="card stat"><small>Total members</small><div class="num"><?= $totalMembers ?></div><em><?= $activeMembers ?> active · <?= $notActivated ?> not activated</em></div>
<div class="card stat"><small>Payment collections</small><div class="num"><?= rupee($paymentTotal) ?></div><em>Approved payments, all time</em></div>
<div class="card stat"><small>Income credited</small><div class="num"><?= rupee($incomeTotal) ?></div><em>Actual earnings</em></div>
<div class="card stat gold"><small>Premium members</small><div class="num"><?= $premiumMembers ?></div><em><?= $kycPending ?> awaiting review →</em></div>
</div>
<div class="grid g2">
<div class="card"><h3>Income distribution</h3><div class="s">Credited amounts by income type</div>
<div class="dist"><?php foreach ($distLabels as $k=>$label): $v=(float)($dist[$k] ?? 0); $pct = $incomeTotal>0 ? round($v/$incomeTotal*100) : 0; ?>
  <div class="r"><p><span><?= $label ?></span><span><?= rupee($v) ?></span></p><div class="t"><i style="width:<?= $pct ?>%;background:var(--em)"></i></div></div>
<?php endforeach; ?></div></div>
<div class="card"><h3>Latest members</h3><div class="s">The newest people in your network</div>
<div class="tw"><table><thead><tr><th>Member</th><th>Package</th><th>Status</th></tr></thead><tbody>
<?php foreach ($latest as $m): ?>
<tr><td><b><?= htmlspecialchars($m['name']) ?></b><br><span class="s"><?= htmlspecialchars($m['member_code']) ?></span></td>
<td><?= $m['package_code']==='none' ? 'Not activated' : ucfirst($m['package_code']) ?></td>
<td><span class="tag <?= $m['status']==='active'?'ok':($m['status']==='blocked'?'bad':'') ?>"><?= ucfirst($m['status']) ?></span></td></tr>
<?php endforeach; if (!$latest): ?><tr><td colspan="3" class="muted-cell">No members yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
