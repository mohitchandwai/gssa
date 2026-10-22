<?php
$PAGE_TITLE = 'Dashboard';
require __DIR__ . '/_top.php';
$pdo = db();

$balance = wallet_balance($memberId);
$sumType = function (string $t) use ($pdo, $memberId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM income_ledger WHERE receiver_id=? AND income_type=?");
    $stmt->execute([$memberId, $t]);
    return (float)$stmt->fetch()['s'];
};
$directIncome = $sumType('direct');
$levelIncome = $sumType('parent_level');
$rewardIncome = $sumType('reward') + $sumType('boost');

$dStmt = $pdo->prepare('SELECT COUNT(*) c FROM members WHERE sponsor_id=?'); $dStmt->execute([$memberId]); $directTeam = (int)$dStmt->fetch()['c'];
$pendStmt = $pdo->prepare("SELECT COUNT(*) c FROM members WHERE sponsor_id=? AND kyc_status='pending'"); $pendStmt->execute([$memberId]); $pendingKyc = (int)$pendStmt->fetch()['c'];

$refLink = rtrim(SITE_URL, '/') . '/register.php?ref=' . urlencode($ME['member_code']);
?>
<div class="hero"><div><h2>Welcome back, <?= htmlspecialchars(explode(' ', $ME['name'])[0]) ?>.</h2>
<p>Share your link. Every referral who completes KYC joins your team.</p>
<?php if ($ME['package_code'] === 'none'): ?><a class="btn" style="display:inline-block;margin-top:8px;text-decoration:none" href="pay.php">Activate a package</a><?php else: ?><a class="btn" style="display:inline-block;margin-top:8px;text-decoration:none" href="withdraw.php">Withdraw earnings</a><?php endif; ?>
</div>
<div class="k"><div><b><?= rupee($balance) ?></b>wallet balance</div><div><b><?= htmlspecialchars($ME['member_code']) ?></b>your ID</div></div></div>

<div class="grid g2" style="margin-bottom:16px">
<div class="card"><h3>Invite and earn</h3><div class="s">Share this link. Referrals join your team after KYC.</div>
<input readonly value="<?= htmlspecialchars($refLink) ?>" onclick="this.select()" style="width:100%">
</div>
<div class="card"><h3>Your package</h3>
<p class="s">Status: <span class="tag <?= $ME['package_code']==='none'?'':'gold' ?>"><?= $ME['package_code']==='none'?'Not activated':ucfirst($ME['package_code']) ?></span>
· KYC: <span class="tag <?= $ME['kyc_status']==='approved'?'ok':'' ?>"><?= ucfirst($ME['kyc_status']) ?></span></p>
</div>
</div>

<div class="grid g4">
<div class="card stat"><small>Direct income</small><div class="num"><?= rupee($directIncome) ?></div><em><?= $directTeam ?> direct referrals</em></div>
<div class="card stat"><small>Level income</small><div class="num"><?= rupee($levelIncome) ?></div><em>From your matrix</em></div>
<div class="card stat gold"><small>Rewards &amp; boost</small><div class="num"><?= rupee($rewardIncome) ?></div><em>Level milestones</em></div>
<div class="card stat"><small>Pending referrals</small><div class="num"><?= $pendingKyc ?></div><em>Waiting for KYC</em></div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
