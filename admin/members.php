<?php
$PAGE_TITLE = 'Members';
require __DIR__ . '/_top.php';
$pdo = db();

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$pkg = $_GET['pkg'] ?? '';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR member_code LIKE ? OR phone LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
if ($pkg !== '') { $where[] = 'package_code = ?'; $params[] = $pkg; }
$sql = 'SELECT m.*, s.member_code sponsor_code FROM members m LEFT JOIN members s ON s.id = m.sponsor_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY m.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<h1>Members</h1><p class="sub">Open a member to edit details, reset password, block access or activate a package.</p>
<div class="card">
<p class="s"><?= count($rows) ?> matching records</p>
<form class="tools" method="get">
<input name="q" placeholder="Search name, member ID or phone" value="<?= htmlspecialchars($q) ?>" style="flex:1;min-width:200px">
<select name="status"><option value="">All statuses</option><?php foreach (['active'=>'Active','inactive'=>'Inactive','blocked'=>'Blocked'] as $k=>$l): ?><option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select>
<select name="pkg"><option value="">All packages</option><option value="starter" <?= $pkg==='starter'?'selected':'' ?>>Starter</option><option value="premium" <?= $pkg==='premium'?'selected':'' ?>>Premium</option></select>
<button class="btn">Apply</button><a class="btn g" href="members.php">Reset</a>
</form>
<div class="tw"><table><thead><tr><th>Member ID</th><th>Name</th><th>Phone</th><th>Status</th><th>Package</th><th>KYC</th><th>Sponsor</th><th>Joined</th></tr></thead><tbody>
<?php foreach ($rows as $m): ?>
<tr onclick="location.href='member.php?id=<?= $m['id'] ?>'" style="cursor:pointer">
<td class="mid"><?= htmlspecialchars($m['member_code']) ?></td>
<td><?= htmlspecialchars($m['name']) ?></td>
<td><?= htmlspecialchars($m['phone']) ?></td>
<td><span class="tag <?= $m['status']==='active'?'ok':($m['status']==='blocked'?'bad':'') ?>"><?= ucfirst($m['status']) ?></span></td>
<td><?= $m['package_code']==='none' ? '<span class="tag">Not activated</span>' : '<span class="tag gold">'.ucfirst($m['package_code']).'</span>' ?></td>
<td><span class="tag <?= $m['kyc_status']==='approved'?'ok':($m['kyc_status']==='rejected'?'bad':'') ?>"><?= ucfirst($m['kyc_status']) ?></span></td>
<td><?= htmlspecialchars($m['sponsor_code'] ?? '-') ?></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($m['created_at']))) ?></td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted-cell">No members match this filter.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
