<?php
require '../config/config.php';
require_admin();

// Check if currently logged in manager is a Super Admin
$currentIsSuper = (int)($_SESSION['admin_user']['is_super'] ?? 0) === 1;

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
                // By default new creators are normal admins (is_super = 0)
                $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,status,is_super) VALUES(?,?,?,'admin','active',0)");
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
            // Fetch target is_super state
            $checkTarget = $pdo->prepare("SELECT is_super FROM users WHERE id = ? AND role = 'admin'");
            $checkTarget->execute([$id]);
            $targetUser = $checkTarget->fetch();

            if (!$targetUser) {
                flash('error', 'Target administrator profile not found.');
            } elseif ((int)($targetUser['is_super'] ?? 0) === 1) {
                flash('error', 'Critical Security Violation: Super Administrators cannot be demoted or modified!');
            } elseif (!$currentIsSuper) {
                flash('error', 'Access Denied: Only a Super Administrator can revoke administrative accounts.');
            } else {
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
                if ($adminCount <= 1) {
                    flash('error', 'At least one active administrator must remain.');
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET role='member', is_super=0 WHERE id=? AND role='admin'");
                    $stmt->execute([$id]);
                    $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE user_id=?")->execute([$id]);
                    flash('success', 'Administrator privileges removed. The account is now a member.');
                }
            }
        }
        header('Location: administrators.php'); exit;
    }

    if ($action === 'reset_admin_password' && $id > 0) {
        $newPassword = trim($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } else {
            // Fetch target is_super state before editing passwords
            $checkTarget = $pdo->prepare("SELECT is_super FROM users WHERE id = ? AND role = 'admin'");
            $checkTarget->execute([$id]);
            $targetUser = $checkTarget->fetch();

            if (!$targetUser) {
                flash('error', 'Target administrator profile not found.');
            } elseif ((int)($targetUser['is_super'] ?? 0) === 1 && !$currentIsSuper) {
                // Normal admins can never reset a Super Admin's password
                flash('error', 'Access Denied: Standard administrators cannot reset a Super Administrator\'s password.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=? AND role='admin'");
                $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
                $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE user_id=? AND role='admin'")->execute([$id]);
                flash('success', 'Administrator password reset and existing sessions revoked.');
            }
        }
        header('Location: administrators.php'); exit;
    }
}

// Fetch is_super alongside credentials
$admins = $pdo->query("SELECT id,name,email,phone,created_at,status,is_super FROM users WHERE role='admin' ORDER BY is_super DESC, created_at ASC")->fetchAll();
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
    <p class="muted">Only authenticated administrators can access this page. Super Administrators have exclusive permissions to demote or manage other administrators.</p>

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
      <?php foreach($admins as $a): 
        $targetIsSuper = (int)($a['is_super'] ?? 0) === 1;
        $isMe = (int)$a['id'] === (int)($_SESSION['admin_user']['id'] ?? 0);
      ?><tr>
        <td>
            <strong><?=e($a['name'])?></strong> 
            <?php if($targetIsSuper): ?><span class="badge" style="background:#d71920;color:#fff;padding:2px 6px;font-size:11px;border-radius:4px;margin-left:5px;">SUPER</span><?php endif; ?>
        </td>
        <td><?=e($a['email'])?></td><td><?=e(ucfirst($a['status']))?></td><td><?=e(date('d M Y',strtotime($a['created_at'])))?></td>
        <td class="actions-cell">
          <?php if (!$isMe): ?>
              <?php if ($targetIsSuper): ?>
                  <!-- Super Admins are untouchable by everyone -->
                  <span style="color:#d71920; font-size:12px; font-weight:bold;">Protected</span>
              <?php elseif ($currentIsSuper): ?>
                  <!-- Regular admins can only be demoted if the current active session is a Super Admin -->
                  <form method="post" style="display:inline" onsubmit="return confirm('Remove administrator privileges from this account?');">
                      <input type="hidden" name="action" value="demote">
                      <input type="hidden" name="id" value="<?=e($a['id'])?>">
                      <button class="mini-btn danger">Make Member</button>
                  </form>
              <?php else: ?>
                  <span style="color:gray; font-size:12px;">Restricted</span>
              <?php endif; ?>
          <?php endif; ?>

          <?php if (!$targetIsSuper || $currentIsSuper || $isMe): ?>
              <!-- Password reset is allowed if target is a normal admin, OR if the current user is a Super Admin, OR if resetting own password -->
              <details class="reset-details" style="display:inline-block; margin-left:5px;">
                  <summary class="mini-btn">Reset Password</summary>
                  <form method="post" class="reset-form">
                      <input type="hidden" name="action" value="reset_admin_password">
