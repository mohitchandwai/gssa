<?php
$PAGE_TITLE = 'Member details';
require __DIR__ . '/_top.php';
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { http_response_code(404); echo '<h1>Member not found</h1><p><a class="lnk" href="members.php">Back to members</a></p>'; require __DIR__.'/_bottom.php'; exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name && $phone) {
            $pdo->prepare('UPDATE members SET name=?, phone=?, email=? WHERE id=?')->execute([$name, $phone, $email, $id]);
            log_activity('member.edit', 'Member #' . $m['member_code']);
            $msg = 'Details updated.';
        }
    } elseif ($action === 'reset_password') {
        $newPass = bin2hex(random_bytes(4)); // 8-char temp password shown once to the admin
        $pdo->prepare('UPDATE members SET password_hash=? WHERE id=?')->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
        log_activity('member.password', 'Member #' . $m['member_code']);
        $msg = 'Password reset. New temporary password: ' . $newPass . ' (share this with the member securely — it will not be shown again).';
    } elseif ($action === 'toggle_block') {
        $new = $m['status'] === 'blocked' ? 'inactive' : 'blocked';
        $pdo->prepare('UPDATE members SET status=? WHERE id=?')->execute([$new, $id]);
        log_activity($new === 'blocked' ? 'member.block' : 'member.unblock', 'Member #' . $m['member_code']);
        $msg = $new === 'blocked' ? 'Member blocked.' : 'Member unblocked.';
    } elseif ($action === 'activate') {
        $pkg = $_POST['package'] ?? 'starter';
        if (!$m['sponsor_id'] && $m['id'] != 1) {
            $msg = 'This member has no sponsor — set one before activating a paid package.';
        } else {
            $ins = $pdo->prepare('INSERT INTO payments (member_id, package_code, amount, status, channel, paid_at) VALUES (?,?,?,?,?,?)');
            $priceRow = $pdo->prepare('SELECT price FROM packages WHERE code=?'); $priceRow->execute([$pkg]); $price = (float)$priceRow->fetch()['price'];
            $ins->execute([$id, $pkg, $price, 'paid', 'admin', date('Y-m-d H:i:s')]);
            $paymentId = (int)$pdo->lastInsertId();
            activate_member_package($id, $pkg, $paymentId);
            log_activity('member.activate', 'Member #' . $m['member_code'], 'Package: ' . $pkg);
            $msg = ucfirst($pkg) . ' package activated and income distributed.';
        }
    }
    $stmt->execute([$id]);
    $m = $stmt->fetch();
}

$balance = wallet_balance($id);
$sponsor = null;
if ($m['sponsor_id']) { $s = $pdo->prepare('SELECT member_code, name FROM members WHERE id=?'); $s->execute([$m['sponsor_id']]); $sponsor = $s->fetch(); }
?>
<h1>Member #<?= htmlspecialchars($m['member_code']) ?></h1><p class="sub"><a class="lnk" href="members.php">← Back to all members</a></p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="grid g2">
<div class="card">
<h3><?= htmlspecialchars($m['name']) ?></h3>
<p class="s">Sponsor: <?= $sponsor ? htmlspecialchars($sponsor['name'].' ('.$sponsor['member_code'].')') : '— (root)' ?> · Joined <?= date('d M Y', strtotime($m['created_at'])) ?></p>
<form method="post">
<input type="hidden" name="action" value="update"><?= csrf_field() ?>
<div class="fr"><div class="field"><label>Full name</label><input name="name" value="<?= htmlspecialchars($m['name']) ?>"></div><div class="field"><label>Phone</label><input name="phone" value="<?= htmlspecialchars($m['phone']) ?>"></div></div>
<div class="field"><label>Email</label><input name="email" value="<?= htmlspecialchars($m['email'] ?? '') ?>"></div>
<button class="btn">Save changes</button>
</form>
</div>
<div class="card">
<h3>Status</h3>
<p class="s">Package: <span class="tag gold"><?= $m['package_code']==='none'?'Not activated':ucfirst($m['package_code']) ?></span> · Status: <span class="tag <?= $m['status']==='active'?'ok':($m['status']==='blocked'?'bad':'') ?>"><?= ucfirst($m['status']) ?></span> · KYC: <span class="tag <?= $m['kyc_status']==='approved'?'ok':'' ?>"><?= ucfirst($m['kyc_status']) ?></span></p>
<p class="s">Available wallet balance: <b><?= rupee($balance) ?></b></p>
<form method="post" style="margin-bottom:10px"><input type="hidden" name="action" value="toggle_block"><?= csrf_field() ?>
<button class="btn g"><?= $m['status']==='blocked' ? 'Unblock member' : 'Block member' ?></button></form>
<form method="post" style="margin-bottom:10px"><input type="hidden" name="action" value="reset_password"><?= csrf_field() ?>
<button class="btn g">Reset password</button></form>
<?php if ($m['package_code']==='none' || $m['status']!=='active'): ?>
<form method="post"><input type="hidden" name="action" value="activate"><?= csrf_field() ?>
<div class="fr"><select name="package"><option value="starter">Starter — ₹49</option><option value="premium">Premium — ₹1555</option></select>
<button class="btn">Activate manually</button></div>
<p class="s">Use this only for an offline/manual payment the admin has verified. It will credit sponsor and matrix income immediately.</p></form>
<?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
