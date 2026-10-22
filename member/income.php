<?php
$PAGE_TITLE = 'My income';
require __DIR__ . '/_top.php';
$pdo = db();

$stmt = $pdo->prepare(
    "SELECT l.*, f.name fname, f.member_code fcode FROM income_ledger l LEFT JOIN members f ON f.id = l.from_member_id
     WHERE l.receiver_id = ? ORDER BY l.id DESC LIMIT 200"
);
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();
$typeLabel = ['direct'=>'Direct','parent_level'=>'Level','reward'=>'Reward','boost'=>'Boost','service'=>'Service','qr'=>'QR'];
$typeClass = ['direct'=>'ok','reward'=>'gold','boost'=>'bad'];
$balance = wallet_balance($memberId);
?>
<h1>My income</h1><p class="sub">Every rupee credited to your account.</p>
<div class="card stat" style="margin-bottom:16px;max-width:260px"><small>Available balance</small><div class="num"><?= rupee($balance) ?></div><em>Ready to withdraw</em></div>
<div class="card"><div class="tw"><table><thead><tr><th>Type</th><th>Amount</th><th>From</th><th>Level</th><th>Status</th><th>Date</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><span class="tag <?= $typeClass[$r['income_type']] ?? '' ?>"><?= $typeLabel[$r['income_type']] ?? $r['income_type'] ?></span></td>
<td><?= rupee($r['amount']) ?></td><td><?= $r['fname'] ? htmlspecialchars($r['fname']) : '-' ?></td>
<td><?= $r['level'] ?? '-' ?></td>
<td><span class="tag <?= $r['status']==='available'?'ok':'' ?>"><?= ucfirst($r['status']) ?></span></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($r['created_at']))) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted-cell">No income yet — invite your team to get started.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
