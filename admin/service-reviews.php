<?php
$PAGE_TITLE = 'Service reviews';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    $stmt = $pdo->prepare('SELECT ss.*, s.payout_method, s.payout_value FROM service_submissions ss JOIN services s ON s.id = ss.service_id WHERE ss.id = ?');
    $stmt->execute([$id]);
    $sub = $stmt->fetch();
    if ($sub && $sub['status'] === 'pending') {
        if ($action === 'approve') {
            $income = $sub['payout_method'] === 'pct'
                ? round((float)$sub['amount'] * (float)$sub['payout_value'] / 100, 2)
                : (float)$sub['payout_value'];
            $pdo->prepare('UPDATE service_submissions SET status="approved", user_income=?, reviewed_by=?, reviewed_at=? WHERE id=?')
                ->execute([$income, current_admin_id(), date('Y-m-d H:i:s'), $id]);
            credit_income((int)$sub['member_id'], null, 'service', $income);
            log_activity('service.approve', 'Submission #' . $id);
            $msg = 'Approved — ' . rupee($income) . ' credited to the member.';
        } else {
            $pdo->prepare('UPDATE service_submissions SET status="rejected", reviewed_by=?, reviewed_at=? WHERE id=?')
                ->execute([current_admin_id(), date('Y-m-d H:i:s'), $id]);
            log_activity('service.reject', 'Submission #' . $id);
            $msg = 'Submission rejected.';
        }
    }
}

$rows = $pdo->query(
    "SELECT ss.*, m.member_code, m.name mname, s.name sname FROM service_submissions ss
     JOIN members m ON m.id = ss.member_id JOIN services s ON s.id = ss.service_id
     ORDER BY ss.id DESC"
)->fetchAll();
?>
<h1>Service reviews</h1><p class="sub">Members submit the amount they spent. Approve to credit their wallet.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card">
<div class="tw"><table><thead><tr><th>Member</th><th>Service</th><th>Amount</th><th>UTR</th><th>Status</th><th>Income</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><b><?= htmlspecialchars($r['mname']) ?></b><br><span class="s"><?= htmlspecialchars($r['member_code']) ?></span></td>
<td><?= htmlspecialchars($r['sname']) ?></td><td><?= rupee($r['amount']) ?></td>
<td><?= htmlspecialchars($r['utr'] ?? '-') ?></td>
<td><span class="tag <?= $r['status']==='approved'?'ok':($r['status']==='rejected'?'bad':'gold') ?>"><?= ucfirst($r['status']) ?></span></td>
<td><?= $r['user_income'] !== null ? rupee($r['user_income']) : '—' ?></td>
<td><?php if ($r['status']==='pending'): ?>
<form method="post" style="display:inline"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="approve"><?= csrf_field() ?><button class="btn" style="padding:5px 12px;font-size:12.5px">Approve</button></form>
<form method="post" style="display:inline"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="reject"><?= csrf_field() ?><button class="btn g" style="padding:5px 12px;font-size:12.5px">Reject</button></form>
<?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted-cell">Nothing submitted yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
