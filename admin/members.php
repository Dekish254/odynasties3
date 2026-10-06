<?php
require '../config/config.php';
require_admin();

// Keep older databases compatible with this updated member-management page.
try {
    $hasStatus = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch();
    if (!$hasStatus) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    }
} catch (PDOException $e) {
    flash('error', 'Could not prepare the member status field.');
}

// Handle admin actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'toggle_status') {
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=? AND role='member'");
        $stmt->execute([$id]);
        flash('success', 'Member status updated.');
        header('Location: members.php'); exit;
    }

    if ($id > 0 && $action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role='member'");
        $stmt->execute([$id]);
        flash('success', 'Member deleted.');
        header('Location: members.php'); exit;
    }

    if ($id > 0 && $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $blood = $_POST['blood_group'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $allowed = ['O+','O-','A+','A-','B+','B-','AB+','AB-'];

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid member name and email.');
        } elseif ($blood !== '' && !in_array($blood, $allowed, true)) {
            flash('error', 'Invalid blood group.');
        } elseif (!in_array($status, ['active','inactive'], true)) {
            flash('error', 'Invalid member status.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, blood_group=?, status=? WHERE id=? AND role='member'");
                $stmt->execute([$name, $email, $phone ?: null, $blood ?: null, $status, $id]);
                flash('success', 'Member details updated.');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'That email address is already in use.' : 'Could not update member.');
            }
        }
        header('Location: members.php?edit=' . $id); exit;
    }
}

$search = trim($_GET['q'] ?? '');
$editId = (int)($_GET['edit'] ?? 0);

if ($search !== '') {
    $stmt = $pdo->prepare("SELECT id,name,email,phone,blood_group,created_at,status FROM users WHERE role='member' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY created_at DESC");
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll();
} else {
    $rows = $pdo->query("SELECT id,name,email,phone,blood_group,created_at,status FROM users WHERE role='member' ORDER BY created_at DESC")->fetchAll();
}

$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT id,name,email,phone,blood_group,created_at,status FROM users WHERE id=? AND role='member'");
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

$title = 'Member Management';
require '../includes/header.php';
?>
<div class="adminnav"><div class="container">
    <a href="index.php">Dashboard</a>
    <a href="members.php">Members</a><a href="online-users.php">Online Users</a><a href="administrators.php">Administrators</a><a href="reports.php">Reports</a><a href="support.php">Support Requests</a>
    <a href="donations.php">Donations</a>
    <a href="events.php">Events</a>
    <a href="news.php">News</a>
    <a href="messages.php">Messages</a>
</div></div>

<section class="section">
<div class="container">
    <?php show_flash(); ?>
    <div class="admin-heading">
        <div>
            <h1>Registered Members</h1>
            <p class="muted">View, search, edit, activate/deactivate and remove registered members.</p>
        </div>
        <div class="member-count"><strong><?= e(count($rows)) ?></strong><span>shown</span></div>
    </div>

    <form method="get" class="searchbar">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name, email or phone...">
        <button class="btn" type="submit">Search</button>
        <?php if ($search !== ''): ?><a class="btn alt dark-alt" href="members.php">Clear</a><?php endif; ?>
    </form>

    <?php if ($editing): ?>
    <div class="editbox">
        <div class="editbox-head"><h2>Edit Member</h2><a href="members.php">Close</a></div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= e($editing['id']) ?>">
            <div class="formgrid">
                <div class="field"><label>Name</label><input required name="name" value="<?= e($editing['name']) ?>"></div>
                <div class="field"><label>Email</label><input required type="email" name="email" value="<?= e($editing['email']) ?>"></div>
                <div class="field"><label>Phone</label><input name="phone" value="<?= e($editing['phone']) ?>"></div>
                <div class="field"><label>Blood Group</label><select name="blood_group"><option value="">Not specified</option><?php foreach(['O+','O-','A+','A-','B+','B-','AB+','AB-'] as $bg): ?><option value="<?= $bg ?>" <?= $editing['blood_group']===$bg?'selected':'' ?>><?= $bg ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Status</label><select name="status"><option value="active" <?= $editing['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $editing['status']==='inactive'?'selected':'' ?>>Inactive</option></select></div>
                <div class="field"><label>Registered</label><input disabled value="<?= e($editing['created_at']) ?>"></div>
            </div>
            <button class="btn" type="submit">Save Changes</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="tablewrap member-table">
        <table class="table">
            <thead><tr><th>#</th><th>Member Name</th><th>Email</th><th>Phone</th><th>Blood</th><th>Registered</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8" class="empty">No registered members found.</td></tr><?php endif; ?>
            <?php foreach($rows as $i=>$r): ?>
            <tr>
                <td><?= e($i+1) ?></td>
                <td><strong><?= e($r['name']) ?></strong></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['phone'] ?: '—') ?></td>
                <td><?= e($r['blood_group'] ?: '—') ?></td>
                <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                <td><span class="status <?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                <td class="actions-cell">
                    <a class="mini-btn" href="members.php?edit=<?= e($r['id']) ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Change this member status?');"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="mini-btn" type="submit"><?= $r['status']==='active'?'Deactivate':'Activate' ?></button></form>
                    <form method="post" onsubmit="return confirm('Permanently delete this member? This cannot be undone.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="mini-btn danger" type="submit">Delete</button></form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</section>
<?php require '../includes/footer.php'; ?>
