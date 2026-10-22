<?php
$PAGE_TITLE = 'KYC reviews';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if ($m) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE members SET kyc_status='approved' WHERE id=?")->execute([$id]);
            // If a package purchase was waiting on KYC, place them in the matrix now.
            if ($m['package_code'] !== 'none' && empty($m['parent_id']) && !empty($m['sponsor_id'])) {
                place_member_in_matrix($id, (int)$m['sponsor_id']);
                check_level_rewards($id);
            }
            log_activity('member.kyc_approve', 'Member #' . $m['member_code']);
            $msg = 'KYC approved for ' . $m['name'] . '.';
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE members SET kyc_status='rejected' WHERE id=?")->execute([$id]);
            log_activity('member.kyc_reject', 'Member #' . $m['member_code']);
            $msg = 'KYC rejected for ' . $m['name'] . '.';
        }
    }
}

$status = $_GET['status'] ?? 'pending';
$sql = 'SELECT * FROM members';
$params = [];
if ($status !== '') { $sql .= ' WHERE kyc_status = ?'; $params[] = $status; }
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<h1>KYC reviews</h1><p class="sub">Approve a member to place them in their sponsor's tree and release matrix income.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card">
<form class="tools" method="get">
<select name="status" onchange="this.form.submit()">
<?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected',''=>'All'] as $k=>$l): ?>
<option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option>
<?php endforeach; ?>
</select>
</form>
<div class="tw"><table><thead><tr><th>Member ID</th><th>Name</th><th>Phone</th><th>Package</th><th>Status</th><th>Documents</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $m):
  $docs = $pdo->prepare('SELECT * FROM kyc_documents WHERE member_id = ?'); $docs->execute([$m['id']]); $docRows = $docs->fetchAll();
?>
<tr>
<td class="mid"><?= htmlspecialchars($m['member_code']) ?></td>
<td><?= htmlspecialchars($m['name']) ?></td>
<td><?= htmlspecialchars($m['phone']) ?></td>
<td><?= $m['package_code']==='none'?'-':ucfirst($m['package_code']) ?></td>
<td><span class="tag <?= $m['kyc_status']==='approved'?'ok':($m['kyc_status']==='rejected'?'bad':'') ?>"><?= ucfirst($m['kyc_status']) ?></span></td>
<td><?php if ($docRows): foreach ($docRows as $d): ?><a class="lnk" href="/uploads/<?= htmlspecialchars($d['file_path']) ?>" target="_blank">View</a> <?php endforeach; else: ?><span class="s">None uploaded</span><?php endif; ?></td>
<td>
<?php if ($m['kyc_status'] !== 'approved'): ?>
<form method="post" style="display:inline"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="approve"><?= csrf_field() ?><button class="btn" style="padding:5px 12px;font-size:12.5px">Approve</button></form>
<?php endif; ?>
<?php if ($m['kyc_status'] !== 'rejected'): ?>
<form method="post" style="display:inline"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="reject"><?= csrf_field() ?><button class="btn g" style="padding:5px 12px;font-size:12.5px">Reject</button></form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted-cell">Nothing here.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
