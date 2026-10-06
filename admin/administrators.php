<?php
require '../config/config.php';
require_admin();

// Admin-only management of administrator accounts.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'create_admin') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Enter a valid name, email and password of at least 8 characters.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,'admin','active')");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                flash('success', 'New administrator created successfully.');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'That email address is already registered.' : 'Could not create administrator.');
            }
        }
        header('Location: administrators.php'); exit;
    }

    if ($action === 'promote' && $id > 0) {
        $stmt = $pdo->prepare("UPDATE users SET role='admin', status='active', must_change_password=0 WHERE id=? AND role='member'");
        $stmt->execute([$id]);
        // Force the promoted account to sign in again with the new role.
        $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE user_id=?")->execute([$id]);
        flash('success', $stmt->rowCount() ? 'The member is now an administrator. They must log in again.' : 'Member could not be promoted.');
        header('Location: administrators.php'); exit;
    }

    if ($action === 'demote' && $id > 0) {
        $currentAdminId = (int)($_SESSION['admin_user']['id'] ?? 0);
        if ($id === $currentAdminId) {
            flash('error', 'You cannot remove your own administrator privileges from this page.');
        } else {
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
            if ($adminCount <= 1) {
                flash('error', 'At least one active administrator must remain.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role='member' WHERE id=? AND role='admin'");
                $stmt->execute([$id]);
                $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE user_id=?")->execute([$id]);
                flash('success', 'Administrator privileges removed. The account is now a member.');
            }
        }
        header('Location: administrators.php'); exit;
    }

    if ($action === 'reset_admin_password' && $id > 0) {
        $newPassword = trim($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=? AND role='admin'");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE user_id=? AND role='admin'")->execute([$id]);
            flash('success', 'Administrator password reset and existing sessions revoked.');
        }
        header('Location: administrators.php'); exit;
    }
}

$admins = $pdo->query("SELECT id,name,email,phone,created_at,status FROM users WHERE role='admin' ORDER BY created_at ASC")->fetchAll();
$members = $pdo->query("SELECT id,name,email,phone,created_at FROM users WHERE role='member' AND status='active' ORDER BY name ASC")->fetchAll();
$title = 'Administrator Management';
require '../includes/header.php';
?>
<div class="adminnav"><div class="container">
    <a href="index.php">Dashboard</a><a href="members.php">Members</a><a href="online-users.php">Online Users</a>
    <a href="administrators.php">Administrators</a><a href="reports.php">Reports</a><a href="support.php">Support Requests</a>
    <a href="donations.php">Donations</a><a href="events.php">Events</a><a href="news.php">News</a><a href="messages.php">Messages</a>
</div></div>
<section class="section"><div class="container">
    <?php show_flash(); ?>
    <h1>Administrator Management</h1>
    <p class="muted">Only authenticated administrators can access this page. You can create administrators or promote existing members.</p>

    <div class="cards" style="grid-template-columns:1fr 1fr;align-items:start">
      <div class="formwrap" style="margin:0;max-width:none">
        <h2>Add Administrator</h2>
        <form method="post">
          <input type="hidden" name="action" value="create_admin">
          <div class="field"><label>Full name</label><input name="name" required></div>
          <div class="field"><label>Email</label><input type="email" name="email" required></div>
          <div class="field"><label>Temporary password</label><input type="password" name="password" minlength="8" required></div>
          <button class="btn">Create Administrator</button>
        </form>
      </div>
      <div class="formwrap" style="margin:0;max-width:none">
        <h2>Promote Existing Member</h2>
        <form method="post">
          <input type="hidden" name="action" value="promote">
          <div class="field"><label>Select member</label><select name="id" required>
            <option value="">Choose a member</option>
            <?php foreach($members as $m): ?><option value="<?=e($m['id'])?>"><?=e($m['name'].' — '.$m['email'])?></option><?php endforeach; ?>
          </select></div>
          <button class="btn">Make Administrator</button>
        </form>
      </div>
    </div>

    <div class="tablewrap" style="margin-top:28px"><table class="table">
      <thead><tr><th>Administrator</th><th>Email</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($admins as $a): ?><tr>
        <td><strong><?=e($a['name'])?></strong></td><td><?=e($a['email'])?></td><td><?=e(ucfirst($a['status']))?></td><td><?=e(date('d M Y',strtotime($a['created_at'])))?></td>
        <td class="actions-cell">
          <?php if ((int)$a['id'] !== (int)($_SESSION['admin_user']['id'] ?? 0)): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Remove administrator privileges from this account?');"><input type="hidden" name="action" value="demote"><input type="hidden" name="id" value="<?=e($a['id'])?>"><button class="mini-btn danger">Make Member</button></form>
          <?php endif; ?>
          <details class="reset-details"><summary class="mini-btn">Reset Password</summary><form method="post" class="reset-form"><input type="hidden" name="action" value="reset_admin_password"><input type="hidden" name="id" value="<?=e($a['id'])?>"><label>New temporary password</label><input type="password" name="new_password" minlength="8" required><button class="mini-btn" type="submit">Reset</button></form></details>
        </td>
      </tr><?php endforeach; ?>
      </tbody>
    </table></div>
</div></section>
<?php require '../includes/footer.php'; ?>
