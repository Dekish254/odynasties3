<?php
require '../config/config.php';
require_admin();

$admins = $pdo->query("SELECT id,name,email FROM users WHERE role='admin' ORDER BY name")->fetchAll();
$adminId = (int)($_GET['admin_id'] ?? 0);
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$where = [];
$params = [];
if ($adminId > 0) { $where[] = 'l.admin_id=?'; $params[] = $adminId; }
if ($from !== '') { $where[] = 'DATE(l.created_at)>=?'; $params[] = $from; }
if ($to !== '') { $where[] = 'DATE(l.created_at)<=?'; $params[] = $to; }
$sql = "SELECT l.*, COALESCE(u.name,l.admin_name) AS current_admin_name
        FROM admin_activity_logs l LEFT JOIN users u ON u.id=l.admin_id";
if ($where) $sql .= " WHERE ".implode(' AND ', $where);
$sql .= " ORDER BY l.created_at DESC LIMIT 500";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $logs = $stmt->fetchAll();

$title='Admin Reports';
require '../includes/header.php';
?>
<div class="adminnav"><div class="container">
<a href="index.php">Dashboard</a><a href="members.php">Members</a><a href="online-users.php">Online Users</a><a href="administrators.php">Administrators</a><a href="reports.php">Reports</a><a href="support.php">Support Requests</a><a href="donations.php">Donations</a><a href="events.php">Events</a><a href="news.php">News</a><a href="messages.php">Messages</a>
</div></div>
<section class="section"><div class="container">
<?php show_flash(); ?>
<h1>Administrator Reports</h1>
<p class="muted">Every administrator has a separate login session. This report records administrator actions so you can see who performed an action and when.</p>
<div class="formwrap" style="max-width:none">
<form method="get" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
<div class="field"><label>Administrator</label><select name="admin_id"><option value="0">All administrators</option><?php foreach($admins as $a): ?><option value="<?=e($a['id'])?>" <?=$adminId===(int)$a['id']?'selected':''?>><?=e($a['name'].' — '.$a['email'])?></option><?php endforeach; ?></select></div>
<div class="field"><label>From</label><input type="date" name="from" value="<?=e($from)?>"></div>
<div class="field"><label>To</label><input type="date" name="to" value="<?=e($to)?>"></div>
<button class="btn">Filter</button>
</form>
</div>
<div class="tablewrap"><table class="table"><thead><tr><th>Date & Time</th><th>Administrator</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php if(!$logs): ?><tr><td colspan="5" class="empty">No administrator activity recorded yet.</td></tr><?php endif; ?>
<?php foreach($logs as $l): ?><tr>
<td><?=e(date('d M Y H:i:s',strtotime($l['created_at'])))?></td>
<td><strong><?=e($l['current_admin_name'] ?: $l['admin_name'] ?: 'Unknown')?></strong></td>
<td><?=e($l['action'])?></td>
<td><?=e($l['details'] ?: '—')?></td>
<td><?=e($l['ip_address'] ?: '—')?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</div></section>
<?php require '../includes/footer.php'; ?>
