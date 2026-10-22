<?php
$PAGE_TITLE = 'Member tree';
require __DIR__ . '/_top.php';
$pdo = db();

$rootCode = trim($_GET['root'] ?? '');
if ($rootCode !== '') {
    $stmt = $pdo->prepare('SELECT id FROM members WHERE member_code = ? OR name LIKE ?');
    $stmt->execute([$rootCode, "%$rootCode%"]);
    $r = $stmt->fetch();
    $rootId = $r ? (int)$r['id'] : 1;
} else {
    $rootId = 1; // the business root member
}
$levelsToShow = max(1, min(5, (int)($_GET['levels'] ?? 3)));

function load_children(PDO $pdo, int $parentId): array
{
    $stmt = $pdo->prepare('SELECT id, member_code, name, package_code, status FROM members WHERE parent_id = ? ORDER BY slot');
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

$rootStmt = $pdo->prepare('SELECT id, member_code, name, package_code, status FROM members WHERE id = ?');
$rootStmt->execute([$rootId]);
$root = $rootStmt->fetch();
?>
<h1>Member tree</h1><p class="sub">Three positions per parent. A new member is placed under their sponsor (or the next open slot) as soon as their package is activated.</p>
<div class="card" style="margin-bottom:16px">
<form class="tools" method="get">
<input name="root" placeholder="Find member by name or ID" value="<?= htmlspecialchars($rootCode) ?>">
<select name="levels"><?php foreach ([1,2,3,4,5] as $lv): ?><option value="<?= $lv ?>" <?= $levelsToShow===$lv?'selected':'' ?>><?= $lv ?> levels</option><?php endforeach; ?></select>
<button class="btn">View tree</button>
</form>
</div>
<?php if (!$root): ?>
<div class="card">No member found.</div>
<?php else: ?>
<div class="card" style="overflow-x:auto">
<?php
function render_node(PDO $pdo, array $node, int $depth, int $maxDepth): void {
    $pkgTag = $node['package_code']==='none' ? '' : '<span class="tag gold">'.ucfirst($node['package_code']).'</span>';
    echo '<div class="node"><div class="card" style="display:inline-block;min-width:150px;text-align:center;margin-bottom:8px"><b>'.htmlspecialchars($node['name']).'</b><br><span class="mid">'.htmlspecialchars($node['member_code']).'</span><br>'.$pkgTag.' <span class="tag '.($node['status']==='active'?'ok':'').'">'.ucfirst($node['status']).'</span></div>';
    if ($depth < $maxDepth) {
        $children = load_children($pdo, (int)$node['id']);
        echo '<div style="display:flex;gap:16px;margin-left:24px;border-left:2px dashed var(--line);padding-left:16px;flex-wrap:wrap">';
        for ($i = 0; $i < 3; $i++) {
            if (isset($children[$i])) {
                render_node($pdo, $children[$i], $depth + 1, $maxDepth);
            } else {
                echo '<div class="card" style="min-width:150px;text-align:center;color:var(--mut);border-style:dashed">+ Open slot '.($i+1).'</div>';
            }
        }
        echo '</div>';
    }
    echo '</div>';
}
render_node($pdo, $root, 0, $levelsToShow);
?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_bottom.php'; ?>
