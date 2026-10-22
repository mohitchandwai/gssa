<?php
$PAGE_TITLE = 'Activity log';
require __DIR__ . '/_top.php';
$pdo = db();

$rows = $pdo->query(
    "SELECT a.*, u.username FROM activity_log a LEFT JOIN admin_users u ON u.id = a.admin_id ORDER BY a.id DESC LIMIT 300"
)->fetchAll();
?>
<h1>Activity log</h1><p class="sub">Administrator changes, payment decisions and the exact settings saved.</p>
<div class="card">
<p class="s"><?= count($rows) ?> events</p>
<div class="tw"><table><thead><tr><th>Event</th><th>Administrator</th><th>Subject</th><th>Time</th><th>Details</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td class="mid"><?= htmlspecialchars($r['event']) ?></td>
<td><?= htmlspecialchars($r['username'] ?? 'System') ?></td>
<td><?= htmlspecialchars($r['subject'] ?? '-') ?></td>
<td><?= htmlspecialchars(date('d M Y, H:i', strtotime($r['created_at']))) ?></td>
<td><?= htmlspecialchars($r['details'] ?? '') ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted-cell">No activity recorded yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/_bottom.php'; ?>
