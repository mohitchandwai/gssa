<?php
$PAGE_TITLE = 'Services';
require __DIR__ . '/_top.php';
$pdo = db();

function member_save_upload(string $field, string $subdir): ?string
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
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return $name;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $serviceId = (int)$_POST['service_id'];
        $amount = (float)$_POST['amount'];
        $utr = trim($_POST['utr'] ?? '');
        if ($amount <= 0) {
            throw new RuntimeException('Enter a valid amount.');
        }
        $shot = member_save_upload('screenshot', 'services');
        $pdo->prepare('INSERT INTO service_submissions (member_id, service_id, amount, utr, screenshot_path, status) VALUES (?,?,?,?,?,"pending")')
            ->execute([$memberId, $serviceId, $amount, $utr, $shot]);
        $msg = 'Submitted — the admin will review and credit your wallet.';
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

$services = $pdo->query('SELECT * FROM services WHERE active = 1 ORDER BY id')->fetchAll();
$mine = $pdo->prepare('SELECT ss.*, s.name sname FROM service_submissions ss JOIN services s ON s.id=ss.service_id WHERE ss.member_id=? ORDER BY ss.id DESC LIMIT 20');
$mine->execute([$memberId]);
$mineRows = $mine->fetchAll();
?>
<h1>Services</h1><p class="sub">Use a service, then submit the amount you spent for review.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="grid g3" style="margin-bottom:16px">
<?php foreach ($services as $s): ?>
<div class="card svc"><div class="ic"><?= strtoupper(substr($s['icon'],0,1)) ?></div><b><?= htmlspecialchars($s['name']) ?></b>
<?php if ($s['url']): ?><a class="lnk" href="<?= htmlspecialchars($s['url']) ?>" target="_blank">Open ↗</a><?php endif; ?>
<form method="post" enctype="multipart/form-data" style="margin-top:10px">
<input type="hidden" name="service_id" value="<?= $s['id'] ?>"><?= csrf_field() ?>
<input name="amount" type="number" step="0.01" placeholder="Amount spent (₹)" required style="margin-bottom:6px;width:100%">
<input name="utr" placeholder="UTR / transaction ref" style="margin-bottom:6px;width:100%">
<input type="file" name="screenshot" accept="image/*" style="margin-bottom:6px;width:100%">
<button class="btn" style="width:100%">Submit for review</button>
</form></div>
<?php endforeach; if (!$services): ?><p class="s">No services available right now.</p><?php endif; ?>
</div>
<div class="card"><h3>My submissions</h3>
<div class="tw"><table><thead><tr><th>Service</th><th>Amount</th><th>Status</th><th>Income</th></tr></thead><tbody>
<?php foreach ($mineRows as $r): ?>
<tr><td><?= htmlspecialchars($r['sname']) ?></td><td><?= rupee($r['amount']) ?></td>
<td><span class="tag <?= $r['status']==='approved'?'ok':($r['status']==='rejected'?'bad':'gold') ?>"><?= ucfirst($r['status']) ?></span></td>
<td><?= $r['user_income']!==null?rupee($r['user_income']):'—' ?></td></tr>
<?php endforeach; if (!$mineRows): ?><tr><td colspan="4" class="muted-cell">Nothing submitted yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
