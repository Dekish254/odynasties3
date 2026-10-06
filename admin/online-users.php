<?php
require '../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'logout_session' && $sessionId > 0) {
        $stmt = $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE id=? AND role='member'");
        $stmt->execute([$sessionId]);
        flash('success', 'The member session has been logged out.');
        header('Location: online-users.php'); exit;
    }

    if ($action === 'logout_all' && $userId > 0) {
        $stmt = $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE user_id=? AND role='member' AND revoked_at IS NULL");
        $stmt->execute([$userId]);
        flash('success', 'All active sessions for this member have been logged out.');
        header('Location: online-users.php'); exit;
    }

    if ($action === 'reset_password' && $userId > 0) {
        $newPassword = trim($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            flash('error', 'The temporary password must be at least 8 characters.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=? AND role='member'");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            // Force all current sessions to end so the member must sign in again.
            $pdo->prepare("UPDATE login_sessions SET revoked_at=NOW() WHERE user_id=? AND role='member' AND revoked_at IS NULL")->execute([$userId]);
            flash('success', 'Password reset. Give the member the temporary password securely; they will be asked to change it after signing in.');
        }
        header('Location: online-users.php'); exit;
    }
}

// Online means the account has sent a request/heartbeat in the last 5 minutes.
$rows = $pdo->query("SELECT ls.id AS session_id, ls.user_id, ls.role, ls.created_at, ls.last_seen,
    u.name, u.email, u.phone, u.status
    FROM login_sessions ls
    JOIN users u ON u.id=ls.user_id
    WHERE ls.role='member' AND ls.revoked_at IS NULL AND ls.last_seen >= (NOW() - INTERVAL 5 MINUTE)
    ORDER BY ls.last_seen DESC")->fetchAll();

$title = 'Online Users';
require '../includes/header.php';
?>
<div class="adminnav"><div class="container">
    <a href="index.php">Dashboard</a>
    <a href="members.php">Members</a>
    <a href="online-users.php">Online Users</a><a href="administrators.php">Administrators</a><a href="reports.php">Reports</a>
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
            <h1>Currently Logged In</h1>
            <p class="muted">Members are considered online when their account has been active within the last 5 minutes.</p>
        </div>
        <div class="member-count"><strong><?= e(count($rows)) ?></strong><span>active sessions</span></div>
    </div>

    <div class="notice-box"><strong>Admin controls:</strong> You can end a member's session or reset a forgotten password. A password reset ends the member's current sessions and requires them to sign in again with the temporary password.</div>

    <div class="tablewrap member-table">
        <table class="table">
            <thead><tr><th>Member</th><th>Email</th><th>Phone</th><th>Last Activity</th><th>Session Started</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">No members are currently active.</td></tr><?php endif; ?>
            <?php foreach($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong><br><span class="muted smalltext"><?= e(ucfirst($r['status'])) ?></span></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['phone'] ?: '—') ?></td>
                
                <td><?= e(date('d M Y H:i:s', strtotime($r['last_seen']))) ?></td>
                <td><?= e(date('d M Y H:i:s', strtotime($r['created_at']))) ?></td>
                <td class="actions-cell">
                    <form method="post" onsubmit="return confirm('Log this member out of this session?');"><input type="hidden" name="action" value="logout_session"><input type="hidden" name="session_id" value="<?= e($r['session_id']) ?>"><button class="mini-btn" type="submit">Log Out</button></form>
                    <form method="post" onsubmit="return confirm('Log this member out from all active sessions?');"><input type="hidden" name="action" value="logout_all"><input type="hidden" name="user_id" value="<?= e($r['user_id']) ?>"><button class="mini-btn" type="submit">Log Out All</button></form>
                    <details class="reset-details"><summary class="mini-btn">Reset Password</summary><form method="post" class="reset-form" onsubmit="return confirm('Reset this member password and end their active sessions?');"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= e($r['user_id']) ?>"><label>Temporary password</label><input type="password" name="new_password" minlength="8" required placeholder="At least 8 characters"><button class="mini-btn" type="submit">Set Password</button></form></details>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</section>
<?php require '../includes/footer.php'; ?>
