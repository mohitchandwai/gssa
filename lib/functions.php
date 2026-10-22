<?php
/**
 * Business logic: member codes, matrix placement, income distribution,
 * level/reward bonuses, wallet balance, withdrawal processing.
 * Include config.php, lib/db.php, lib/auth.php before this file.
 */

function rupee($amount): string
{
    return '₹' . number_format((float)$amount, 0);
}

function new_member_code(): string
{
    $pdo = db();
    do {
        $code = (string)random_int(1000000, 9999999);
        $stmt = $pdo->prepare('SELECT 1 FROM members WHERE member_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

/** Find the first ancestor (starting at $sponsorId) with fewer than 3 direct
 *  children, breadth-first, and return its id — this is where a new member
 *  gets placed under (spillover matrix placement, 3 positions per parent). */
function find_matrix_slot(int $sponsorId): int
{
    $pdo = db();
    $queue = [$sponsorId];
    while ($queue) {
        $current = array_shift($queue);
        $stmt = $pdo->prepare('SELECT id FROM members WHERE parent_id = ? ORDER BY slot');
        $stmt->execute([$current]);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($children) < 3) {
            return $current;
        }
        foreach ($children as $c) {
            $queue[] = (int)$c;
        }
    }
    return $sponsorId; // unreachable in practice
}

/** Place $memberId under $sponsorId's matrix tree (sets parent_id + slot). */
function place_member_in_matrix(int $memberId, int $sponsorId): void
{
    $pdo = db();
    $parentId = find_matrix_slot($sponsorId);
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM members WHERE parent_id = ?');
    $stmt->execute([$parentId]);
    $slot = (int)$stmt->fetch()['c'] + 1;
    $stmt = $pdo->prepare('UPDATE members SET parent_id = ?, slot = ? WHERE id = ?');
    $stmt->execute([$parentId, $slot, $memberId]);
}

/** Direct ancestor chain of $memberId, nearest first, up to $maxLevels. */
function ancestor_chain(int $memberId, int $maxLevels): array
{
    $pdo = db();
    $chain = [];
    $current = $memberId;
    for ($i = 0; $i < $maxLevels; $i++) {
        $stmt = $pdo->prepare('SELECT parent_id FROM members WHERE id = ?');
        $stmt->execute([$current]);
        $row = $stmt->fetch();
        if (!$row || !$row['parent_id']) {
            break;
        }
        $chain[] = (int)$row['parent_id'];
        $current = (int)$row['parent_id'];
    }
    return $chain;
}

function credit_income(int $receiverId, ?int $fromMemberId, string $type, float $amount, ?int $level = null, ?int $paymentId = null): void
{
    if ($amount <= 0) {
        return;
    }
    $stmt = db()->prepare(
        'INSERT INTO income_ledger (receiver_id, from_member_id, income_type, amount, level, payment_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$receiverId, $fromMemberId, $type, $amount, $level, $paymentId]);
}

function wallet_balance(int $memberId): float
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(amount),0) s FROM income_ledger WHERE receiver_id = ? AND status = 'available'"
    );
    $stmt->execute([$memberId]);
    return (float)$stmt->fetch()['s'];
}

/** Count members exactly $depth matrix-levels below $rootId (BFS). */
function count_at_depth(int $rootId, int $depth): int
{
    $pdo = db();
    $level = [$rootId];
    for ($d = 0; $d < $depth; $d++) {
        if (!$level) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($level), '?'));
        $stmt = $pdo->prepare("SELECT id FROM members WHERE parent_id IN ($in)");
        $stmt->execute($level);
        $level = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    return count($level);
}

/** After a member is placed/activated, check each ancestor's level targets
 *  and credit the one-time reward + boost the first time a level fills up. */
function check_level_rewards(int $memberId): void
{
    $pdo = db();
    $levels = $pdo->query('SELECT level, target_ids, reward_per_id, boost_per_id FROM level_settings ORDER BY level')->fetchAll();
    $maxLevel = $levels ? (int)end($levels)['level'] : 0;
    $ancestors = ancestor_chain($memberId, $maxLevel);

    foreach ($ancestors as $distance => $ancestorId) {
        $level = $distance + 1; // nearest ancestor = level 1
        $setting = null;
        foreach ($levels as $l) {
            if ((int)$l['level'] === $level) {
                $setting = $l;
                break;
            }
        }
        if (!$setting) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM level_awards WHERE member_id = ? AND level = ?');
        $stmt->execute([$ancestorId, $level]);
        if ($stmt->fetch()) {
            continue; // already awarded
        }
        $count = count_at_depth($ancestorId, $level);
        if ($count >= (int)$setting['target_ids']) {
            $rewardTotal = (float)$setting['reward_per_id'] * (int)$setting['target_ids'];
            $boostTotal = (float)$setting['boost_per_id'] * (int)$setting['target_ids'];
            credit_income($ancestorId, $memberId, 'reward', $rewardTotal, $level);
            credit_income($ancestorId, $memberId, 'boost', $boostTotal, $level);
            $ins = $pdo->prepare('INSERT INTO level_awards (member_id, level) VALUES (?, ?)');
            $ins->execute([$ancestorId, $level]);
        }
    }
}

/**
 * Activate a member on a package: mark active, pay sponsor direct income,
 * pay matrix/parent income up the chain to Premium ancestors, then check
 * for completed-level reward/boost bonuses. Call this once per successful
 * payment (Razorpay webhook, admin manual approval, or QR approval).
 */
function activate_member_package(int $memberId, string $packageCode, ?int $paymentId = null): void
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM packages WHERE code = ?');
    $stmt->execute([$packageCode]);
    $pkg = $stmt->fetch();
    if (!$pkg) {
        throw new RuntimeException('Unknown package: ' . $packageCode);
    }

    $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    if (!$member) {
        throw new RuntimeException('Unknown member: ' . $memberId);
    }

    $firstActivation = $member['status'] !== 'active';

    $pdo->prepare("UPDATE members SET package_code = ?, status = 'active' WHERE id = ?")
        ->execute([$packageCode, $memberId]);

    // Matrix placement happens once, at first activation, under the sponsor.
    if ($firstActivation && empty($member['parent_id']) && !empty($member['sponsor_id'])) {
        place_member_in_matrix($memberId, (int)$member['sponsor_id']);
    }

    // Sponsor direct income (one-time per activation/upgrade).
    if (!empty($member['sponsor_id']) && (float)$pkg['sponsor_income'] > 0) {
        credit_income((int)$member['sponsor_id'], $memberId, 'direct', (float)$pkg['sponsor_income'], null, $paymentId);
    }

    // Parent / level income up the matrix chain, Premium ancestors only.
    if ((float)$pkg['matrix_income'] > 0) {
        $chain = ancestor_chain($memberId, (int)$pkg['matrix_levels']);
        foreach ($chain as $i => $ancestorId) {
            $stmt = $pdo->prepare("SELECT package_code FROM members WHERE id = ?");
            $stmt->execute([$ancestorId]);
            $a = $stmt->fetch();
            if ($a && $a['package_code'] === 'premium') {
                credit_income($ancestorId, $memberId, 'parent_level', (float)$pkg['matrix_income'], $i + 1, $paymentId);
            }
        }
    }

    check_level_rewards($memberId);
}

/** Eligibility + deduction maths for one member's available balance. */
function withdrawal_breakdown(float $gross, float $adminPct, float $tdsPct): array
{
    $admin = round($gross * $adminPct / 100, 2);
    $tds = round($gross * $tdsPct / 100, 2);
    $net = round($gross - $admin - $tds, 2);
    return [$admin, $tds, $net];
}

/**
 * Queue withdrawal requests for every eligible, KYC-approved, non-blocked
 * member whose available ledger balance meets the minimum. Marks the used
 * ledger rows as withdrawn and creates one 'pending' withdrawals row per
 * member. Admin later marks it 'paid' once the real bank transfer is done.
 * Returns how many requests were created.
 */
function process_eligible_withdrawals(): int
{
    $pdo = db();
    $settings = $pdo->query('SELECT * FROM withdrawal_settings WHERE id = 1')->fetch();
    if (!$settings || !$settings['auto_enabled']) {
        return 0;
    }

    $members = $pdo->query(
        "SELECT id FROM members WHERE status = 'active' AND kyc_status = 'approved'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $created = 0;
    foreach ($members as $memberId) {
        $memberId = (int)$memberId;
        $stmt = $pdo->prepare(
            "SELECT id, amount FROM income_ledger WHERE receiver_id = ? AND status = 'available'"
        );
        $stmt->execute([$memberId]);
        $rows = $stmt->fetchAll();
        $gross = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
        if ($gross < (float)$settings['min_amount'] || !$rows) {
            continue;
        }
        [$admin, $tds, $net] = withdrawal_breakdown($gross, (float)$settings['admin_charge_pct'], (float)$settings['tds_pct']);

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                "INSERT INTO withdrawals (member_id, cycle_date, gross, admin_charge, tds, net, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            );
            $ins->execute([$memberId, date('Y-m-d'), $gross, $admin, $tds, $net]);
            $withdrawalId = (int)$pdo->lastInsertId();

            $ids = array_map(fn($r) => (int)$r['id'], $rows);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE income_ledger SET status = 'withdrawn', withdrawal_id = ? WHERE id IN ($in)");
            $upd->execute(array_merge([$withdrawalId], $ids));

            $pdo->commit();
            $created++;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    return $created;
}
