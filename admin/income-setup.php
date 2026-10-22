<?php
$PAGE_TITLE = 'Income setup';
require __DIR__ . '/_top.php';
$pdo = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (['starter', 'premium'] as $code) {
        $pdo->prepare('UPDATE packages SET price=?, sponsor_income=?, matrix_income=? WHERE code=?')->execute([
            (float)$_POST[$code . '_price'], (float)$_POST[$code . '_sponsor'], (float)$_POST[$code . '_matrix'], $code,
        ]);
    }
    foreach ($_POST['level'] ?? [] as $level => $vals) {
        $pdo->prepare('UPDATE level_settings SET reward_per_id=?, boost_per_id=? WHERE level=?')
            ->execute([(float)$vals['reward'], (float)$vals['boost'], (int)$level]);
    }
    log_activity('income_setup.update');
    $msg = 'Income setup saved. This applies to new purchases and future level completions only.';
}

$pkgs = [];
foreach ($pdo->query('SELECT * FROM packages') as $p) { $pkgs[$p['code']] = $p; }
$levels = $pdo->query('SELECT * FROM level_settings ORDER BY level')->fetchAll();
?>
<h1>Income setup</h1><p class="sub">Set package prices, referral payouts and the rates for every level.</p>
<?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="alert ok">Changes apply to new orders and future level completions. Existing earnings stay unchanged. Set an amount to zero to pay no income for that item.</div>
<form method="post"><?= csrf_field() ?>
<div class="grid g2" style="margin-bottom:16px">
<div class="card"><h3>Starter package</h3>
<div class="field"><label>Package price (₹)</label><input type="number" step="0.01" name="starter_price" value="<?= htmlspecialchars($pkgs['starter']['price'] ?? 0) ?>"></div>
<div class="field"><label>Sponsor / Direct income (₹)</label><input type="number" step="0.01" name="starter_sponsor" value="<?= htmlspecialchars($pkgs['starter']['sponsor_income'] ?? 0) ?>"></div>
<div class="field"><label>Matrix parent income (₹)</label><input type="number" step="0.01" name="starter_matrix" value="<?= htmlspecialchars($pkgs['starter']['matrix_income'] ?? 0) ?>"></div>
<p class="s">Starter begins with no referral payout by default.</p></div>
<div class="card"><h3>Premium package</h3>
<div class="field"><label>Package price (₹)</label><input type="number" step="0.01" name="premium_price" value="<?= htmlspecialchars($pkgs['premium']['price'] ?? 0) ?>"></div>
<div class="field"><label>Sponsor / Direct income (₹)</label><input type="number" step="0.01" name="premium_sponsor" value="<?= htmlspecialchars($pkgs['premium']['sponsor_income'] ?? 0) ?>"></div>
<div class="field"><label>Matrix parent income (₹)</label><input type="number" step="0.01" name="premium_matrix" value="<?= htmlspecialchars($pkgs['premium']['matrix_income'] ?? 0) ?>"></div>
<p class="s">Premium parent income pays each eligible Premium ancestor at levels 1–<?= (int)($pkgs['premium']['matrix_levels'] ?? 10) ?>.</p></div>
</div>
<div class="card"><h3>Level rewards &amp; boost rates</h3><div class="s">Amounts per qualifying Premium ID, credited once when a complete level reaches its target.</div>
<div class="tw"><table><thead><tr><th>Level</th><th>Target IDs</th><th>Reward / ID (₹)</th><th>Reward total</th><th>Boost / ID (₹)</th><th>Boost total</th></tr></thead><tbody>
<?php foreach ($levels as $l): ?>
<tr><td><b>Level <?= $l['level'] ?></b></td><td><?= number_format($l['target_ids']) ?></td>
<td><input type="number" step="0.01" name="level[<?= $l['level'] ?>][reward]" value="<?= htmlspecialchars($l['reward_per_id']) ?>" style="width:90px"></td>
<td><?= rupee($l['reward_per_id'] * $l['target_ids']) ?></td>
<td><input type="number" step="0.01" name="level[<?= $l['level'] ?>][boost]" value="<?= htmlspecialchars($l['boost_per_id']) ?>" style="width:90px"></td>
<td><?= rupee($l['boost_per_id'] * $l['target_ids']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<button class="btn" style="margin-top:14px">Save income setup</button>
</div></form>
<?php require __DIR__ . '/_bottom.php'; ?>
