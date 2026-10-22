<?php
$PAGE_TITLE = 'Income reports';
require __DIR__ . '/_top.php';
$pdo = db();

$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$sql = "SELECT l.*, r.member_code rcode, r.name rname, f.member_code fcode, f.name fname
        FROM income_ledger l JOIN members r ON r.id = l.receiver_id LEFT JOIN members f ON f.id = l.from_member_id";
$where = []; $params = [];
if ($q !== '') { $where[] = '(r.name LIKE ? OR r.member_code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($type !== '') { $where[] = 'l.income_type = ?'; $params[] = $type; }
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY l.id DESC LIMIT 300';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();
$total = array_sum(array_map(fn($r)=>(float)$r['amount'], $rows));

$typeLabel = ['direct'=>'Direct','parent_level'=>'Parent / Level','reward'=>'Reward','boost'=>'Boost','service'=>'Service','qr'=>'QR'];
$typeClass = ['direct'=>'ok','reward'=>'gold','boost'=>'bad'];
?>
<h1>Income reports</h1><p class="sub">Every credited rupee, organised by member, income type and date.</p>
<div class="card">
<p class="s"><?= count($rows) ?> matching records · Income total <?= rupee($total) ?></p>
<form class="tools" method="get">
<input name="q" placeholder="Search members" value="<?= htmlspecialchars($q) ?>">
<select name="type"><option value="">All income types</option><?php foreach ($typeLabel as $k=>$l): ?><option value="<?= $k ?>" <?= $type===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
<button class="btn">Apply</button>
</form>
<div class="tw"><table><thead><tr><th>Entry</th><th>Receiver</th><th>Income type</th><th>Amount</th><th>Income from</th><th>Level</th><th>Status</th><th>Earned on</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td>#<?= $r['id'] ?></td>
<td><b><?= htmlspecialchars($r['rname']) ?></b><br><span class="s"><?= htmlspecialchars($r['rcode']) ?></span></td>
<td><span class="tag <?= $typeClass[$r['income_type']] ?? '' ?>"><?= $typeLabel[$r['income_type']] ?? $r['income_type'] ?></span></td>
<td><?= rupee($r['amount']) ?></td>
<td><?= $r['fname'] ? htmlspecialchars($r['fname']) : '-' ?></td>
<td><?= $r['level'] ?? '-' ?></td>
<td><span class="tag <?= $r['status']==='available'?'ok':'' ?>"><?= ucfirst($r['status']) ?></span></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($r['created_at']))) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted-cell">No income recorded yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
