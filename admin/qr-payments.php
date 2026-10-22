<?php
$PAGE_TITLE = 'QR payments';
require __DIR__ . '/_top.php';
$pdo = db();

function save_upload(string $field, string $subdir): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('File is too large (max 5 MB).');
    }
    $info = getimagesize($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($allowed[$info['mime']])) {
        throw new RuntimeException('Only JPG, PNG or WebP images are allowed.');
    }
    $name = $subdir . '/' . bin2hex(random_bytes(12)) . '.' . $allowed[$info['mime']];
    $dest = UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return $name;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_qr') {
            $path = save_upload('qr_image', 'qr');
            $cur = $pdo->query('SELECT qr_image_path FROM qr_settings WHERE id=1')->fetch();
            $path = $path ?: ($cur['qr_image_path'] ?? null);
            $pdo->prepare('UPDATE qr_settings SET company_name=?, upi_id=?, instructions=?, qr_image_path=?, enabled=? WHERE id=1')
                ->execute([trim($_POST['company_name'] ?? ''), trim($_POST['upi_id'] ?? ''), trim($_POST['instructions'] ?? ''), $path, isset($_POST['enabled']) ? 1 : 0]);
            log_activity('qr.settings_update');
            $msg = 'Company QR settings saved.';
        } elseif ($action === 'approve' || $action === 'reject') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('SELECT * FROM qr_payments WHERE id=?'); $stmt->execute([$id]); $qp = $stmt->fetch();
            if ($qp && $qp['status'] === 'pending') {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                $pdo->prepare('UPDATE qr_payments SET status=?, reviewed_by=?, reviewed_at=? WHERE id=?')
                    ->execute([$newStatus, current_admin_id(), date('Y-m-d H:i:s'), $id]);
                if ($action === 'approve') {
                    $ins = $pdo->prepare('INSERT INTO payments (member_id, package_code, amount, status, channel, paid_at) VALUES (?,?,?,?,?,?)');
                    $ins->execute([$qp['member_id'], $qp['package_code'], $qp['amount'], 'paid', 'qr', date('Y-m-d H:i:s')]);
                    $paymentId = (int)$pdo->lastInsertId();
                    activate_member_package((int)$qp['member_id'], $qp['package_code'], $paymentId);
                }
                log_activity('qr.' . $newStatus, 'QR payment #' . $id);
                $msg = 'QR payment ' . $newStatus . '.';
            }
        }
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

$settings = $pdo->query('SELECT * FROM qr_settings WHERE id=1')->fetch();
$status = $_GET['status'] ?? '';
$sql = "SELECT q.*, m.member_code, m.name FROM qr_payments q JOIN members m ON m.id=q.member_id";
$params = [];
if ($status !== '') { $sql .= ' WHERE q.status = ?'; $params[] = $status; }
$sql .= ' ORDER BY q.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<h1>QR payments</h1><p class="sub">Verify the bank credit before approval. Approval activates the package and distributes income automatically.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:16px"><h3>Upload company payment QR</h3>
<div class="s">Upload the company receiving-account QR shown to members when they select Pay by QR.</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="save_qr"><?= csrf_field() ?>
<div class="fr"><div class="field"><label>Company / payee name</label><input name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"></div>
<div class="field"><label>UPI ID (optional)</label><input name="upi_id" value="<?= htmlspecialchars($settings['upi_id'] ?? '') ?>"></div></div>
<div class="field"><label>Payment instructions</label><textarea name="instructions" rows="3"><?= htmlspecialchars($settings['instructions'] ?? '') ?></textarea></div>
<div class="field"><label>Upload / replace company QR (JPG, PNG, WebP; up to 5 MB)</label><input type="file" name="qr_image" accept="image/*"></div>
<?php if (!empty($settings['qr_image_path'])): ?><p class="s">Current: <a class="lnk" href="/uploads/<?= htmlspecialchars($settings['qr_image_path']) ?>" target="_blank">view QR</a></p><?php endif; ?>
<label style="display:flex;gap:8px;align-items:center;margin:10px 0"><input type="checkbox" name="enabled" <?= !empty($settings['enabled'])?'checked':'' ?>>Enable QR payments</label>
<button class="btn">Save company QR</button>
</form></div>
<div class="card"><div class="tools">
<form method="get"><select name="status" onchange="this.form.submit()"><?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected',''=>'All'] as $k=>$l): ?><option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></form>
</div>
<div class="tw"><table><thead><tr><th>Member</th><th>Package / amount</th><th>UTR</th><th>Screenshot</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $q): ?>
<tr><td><b><?= htmlspecialchars($q['name']) ?></b><br><span class="s"><?= htmlspecialchars($q['member_code']) ?></span></td>
<td><?= ucfirst($q['package_code']) ?> · <?= rupee($q['amount']) ?></td>
<td><?= htmlspecialchars($q['utr'] ?? '-') ?></td>
<td><?php if ($q['screenshot_path']): ?><a class="lnk" href="/uploads/<?= htmlspecialchars($q['screenshot_path']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></td>
<td><span class="tag <?= $q['status']==='approved'?'ok':($q['status']==='rejected'?'bad':'gold') ?>"><?= ucfirst($q['status']) ?></span></td>
<td><?php if ($q['status']==='pending'): ?>
<form method="post" style="display:inline"><input type="hidden" name="id" value="<?= $q['id'] ?>"><input type="hidden" name="action" value="approve"><?= csrf_field() ?><button class="btn" style="padding:5px 12px;font-size:12.5px">Approve</button></form>
<form method="post" style="display:inline"><input type="hidden" name="id" value="<?= $q['id'] ?>"><input type="hidden" name="action" value="reject"><?= csrf_field() ?><button class="btn g" style="padding:5px 12px;font-size:12.5px">Reject</button></form>
<?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted-cell">No QR payments found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
