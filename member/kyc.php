<?php
$PAGE_TITLE = 'KYC';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $path = member_save_upload_kyc('document', 'kyc');
        if (!$path) {
            throw new RuntimeException('Please choose a file to upload.');
        }
        $pdo->prepare('INSERT INTO kyc_documents (member_id, doc_type, file_path) VALUES (?,?,?)')
            ->execute([$memberId, trim($_POST['doc_type'] ?? 'identity'), $path]);
        $pdo->prepare("UPDATE members SET kyc_status='pending' WHERE id=?")->execute([$memberId]);
        $msg = 'Document uploaded. The admin will review it shortly.';
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

function member_save_upload_kyc(string $field, string $subdir): ?string
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
        throw new RuntimeException('Only JPG, PNG or WebP images are allowed (upload a clear photo of your document).');
    }
    $name = $subdir . '/' . bin2hex(random_bytes(12)) . '.' . $allowed[$info['mime']];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return $name;
}

$docs = $pdo->prepare('SELECT * FROM kyc_documents WHERE member_id = ? ORDER BY id DESC');
$docs->execute([$memberId]);
$docRows = $docs->fetchAll();
?>
<h1>KYC</h1><p class="sub">Upload a government ID so we can verify your account and release your matrix placement and income.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:16px">
<p class="s">Current status: <span class="tag <?= $ME['kyc_status']==='approved'?'ok':($ME['kyc_status']==='rejected'?'bad':'gold') ?>"><?= ucfirst($ME['kyc_status']) ?></span></p>
<?php if ($ME['kyc_status'] !== 'approved'): ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="fr"><div class="field"><label>Document type</label><select name="doc_type"><option value="identity">ID proof (Aadhaar / PAN / Passport)</option><option value="address">Address proof</option><option value="bank">Bank passbook / cancelled cheque</option></select></div>
<div class="field"><label>Upload (JPG, PNG, WebP; up to 5 MB)</label><input type="file" name="document" accept="image/*" required></div></div>
<button class="btn">Submit for review</button></form>
<?php endif; ?>
</div>
<div class="card"><h3>Your uploads</h3>
<div class="tw"><table><thead><tr><th>Type</th><th>File</th><th>Uploaded</th></tr></thead><tbody>
<?php foreach ($docRows as $d): ?>
<tr><td><?= htmlspecialchars(ucfirst($d['doc_type'])) ?></td><td><a class="lnk" href="/uploads/<?= htmlspecialchars($d['file_path']) ?>" target="_blank">View</a></td>
<td><?= htmlspecialchars(date('d M Y', strtotime($d['created_at']))) ?></td></tr>
<?php endforeach; if (!$docRows): ?><tr><td colspan="3" class="muted-cell">No documents uploaded yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
