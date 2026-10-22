<?php
$PAGE_TITLE = 'Services';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $pdo->prepare('INSERT INTO services (name, url, icon, payout_method, payout_value, active) VALUES (?,?,?,?,?,1)')
                ->execute([$name, trim($_POST['url'] ?? ''), $_POST['icon'] ?? 'phone', $_POST['method'] ?? 'pct', (float)($_POST['value'] ?? 0)]);
            log_activity('service.add', $name);
            $msg = 'Service added.';
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE services SET active = 1 - active WHERE id = ?')->execute([$id]);
    }
}

$icons = ['phone','money','watch','headphones','game','shopping','education','travel','hotel','food','health','electricity','water','gas','internet','bank','wallet','insurance','car','train','ticket','gift','work','support','news','video','home','tools','star','link','bike','solar','card','chart','fund'];
$services = $pdo->query('SELECT * FROM services ORDER BY id DESC')->fetchAll();
?>
<h1>Services</h1><p class="sub">Create useful shortcuts for your members. Active services appear in the member app automatically.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card" style="margin-bottom:16px"><h3>Add a service</h3><div class="s">Choose a name, destination and icon</div>
<form method="post"><input type="hidden" name="action" value="add"><?= csrf_field() ?>
<div class="fr"><div class="field"><label>Service name</label><input name="name" placeholder="e.g. Mobile recharge" required></div><div class="field"><label>Service URL (optional)</label><input name="url" placeholder="Add URL now or later"></div></div>
<label style="font-size:12.5px;color:var(--mut)">Choose an icon</label>
<select name="icon" style="margin:6px 0 14px"><?php foreach ($icons as $ic): ?><option value="<?= $ic ?>"><?= ucfirst($ic) ?></option><?php endforeach; ?></select>
<div class="fr"><div class="field"><label>Default member payout method</label><select name="method"><option value="pct">Percentage of submitted amount</option><option value="fixed">Fixed amount (₹)</option></select></div>
<div class="field"><label>Default payout value (% or ₹)</label><input name="value" type="number" step="0.01" value="0"></div></div>
<button class="btn">Add service</button></form></div>
<div class="grid g3">
<?php foreach ($services as $s): ?>
<div class="card svc"><div class="ic"><?= strtoupper(substr($s['icon'],0,1)) ?></div><b><?= htmlspecialchars($s['name']) ?></b>
<span class="s"><?= htmlspecialchars($s['url'] ?: '(no URL set)') ?></span>
<span class="pr"><?= $s['payout_method']==='pct' ? $s['payout_value'].'%' : rupee($s['payout_value']) ?></span>
<span class="tag <?= $s['active']?'ok':'bad' ?>"><?= $s['active']?'Active':'Hidden' ?></span>
<form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $s['id'] ?>"><?= csrf_field() ?>
<button class="btn g" style="margin-top:8px;font-size:12.5px;padding:5px 12px"><?= $s['active']?'Hide':'Activate' ?></button></form>
</div>
<?php endforeach; if (!$services): ?><p class="s">No services yet — add your first one above.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
