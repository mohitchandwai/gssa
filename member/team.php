<?php
$PAGE_TITLE = 'My team';
require __DIR__ . '/_top.php';
$pdo = db();

$stmt = $pdo->prepare('SELECT member_code, name, phone, package_code, status, kyc_status, created_at FROM members WHERE sponsor_id = ? ORDER BY id DESC');
$stmt->execute([$memberId]);
$direct = $stmt->fetchAll();
?>
<h1>My team</h1><p class="sub">Everyone you referred directly. They join the matrix once their KYC is approved.</p>
<div class="card">
<p class="s"><?= count($direct) ?> direct referrals</p>
<div class="tw"><table><thead><tr><th>Name</th><th>Phone</th><th>Package</th><th>Status</th><th>KYC</th><th>Joined</th></tr></thead><tbody>
<?php foreach ($direct as $d): ?>
<tr><td><b><?= htmlspecialchars($d['name']) ?></b><br><span class="s"><?= htmlspecialchars($d['member_code']) ?></span></td>
<td><?= htmlspecialchars($d['phone']) ?></td>
<td><?= $d['package_code']==='none'?'-':ucfirst($d['package_code']) ?></td>
<td><span class="tag <?= $d['status']==='active'?'ok':'' ?>"><?= ucfirst($d['status']) ?></span></td>
<td><span class="tag <?= $d['kyc_status']==='approved'?'ok':'' ?>"><?= ucfirst($d['kyc_status']) ?></span></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($d['created_at']))) ?></td></tr>
<?php endforeach; if (!$direct): ?><tr><td colspan="6" class="muted-cell">No referrals yet — share your link from the Dashboard.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
