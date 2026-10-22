<?php
$PAGE_TITLE = 'Payments';
require __DIR__ . '/_top.php';
$pdo = db();

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$sql = "SELECT p.*, m.member_code, m.name FROM payments p JOIN members m ON m.id = p.member_id";
$where = []; $params = [];
if ($q !== '') { $where[] = '(m.name LIKE ? OR m.member_code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($status !== '') { $where[] = 'p.status = ?'; $params[] = $status; }
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY p.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();
$total = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='paid'")->fetch()['s'];
?>
<h1>Payments</h1><p class="sub">Razorpay orders, successful payments and manual/admin-recorded payments.</p>
<div class="card">
<p class="s"><?= count($rows) ?> records shown · Paid total <?= rupee($total) ?></p>
<form class="tools" method="get">
<input name="q" placeholder="Search member" value="<?= htmlspecialchars($q) ?>">
<select name="status"><option value="">All statuses</option><?php foreach (['created'=>'Created','paid'=>'Paid','failed'=>'Failed'] as $k=>$l): ?><option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
<button class="btn">Apply</button>
</form>
<div class="tw"><table><thead><tr><th>Member</th><th>Package</th><th>Amount</th><th>Channel</th><th>Order ID</th><th>Status</th><th>Date</th></tr></thead><tbody>
<?php foreach ($rows as $p): ?>
<tr><td><b><?= htmlspecialchars($p['name']) ?></b><br><span class="s"><?= htmlspecialchars($p['member_code']) ?></span></td>
<td><?= ucfirst($p['package_code']) ?></td><td><?= rupee($p['amount']) ?></td>
<td><?= htmlspecialchars(ucfirst($p['channel'])) ?></td>
<td><?= htmlspecialchars($p['razorpay_order_id'] ?? '-') ?></td>
<td><span class="tag <?= $p['status']==='paid'?'ok':($p['status']==='failed'?'bad':'gold') ?>"><?= ucfirst($p['status']) ?></span></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($p['created_at']))) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted-cell">No payments yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
