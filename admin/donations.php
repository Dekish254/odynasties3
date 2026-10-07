<?php 
require '../config/config.php'; 
require_admin(); 

// --- APPR0VAL BACKEND ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_donation') {
    $donation_id = (int)($_POST['donation_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE donations SET status = 'completed' WHERE id = ?");
    $stmt->execute([$donation_id]);
    flash('success', 'Donation successfully verified and marked as completed!');
    header('Location: donations.php'); 
    exit;
}

$rows=$pdo->query('SELECT * FROM donations ORDER BY created_at DESC')->fetchAll(); 
$title='Donations'; 
require '../includes/header.php'; 
?>
<div class="adminnav">
    <div class="container">
        <a href="index.php">Dashboard</a>
        <a href="members.php">Members</a>
        <a href="online-users.php">Online Users</a>
        <a href="administrators.php">Administrators</a>
        <a href="reports.php">Reports</a>
        <a href="support.php">Support Requests</a>
        <a href="donations.php">Donations</a>
        <a href="events.php">Events</a>
        <a href="news.php">News</a>
        <a href="messages.php">Messages</a>
    </div>
</div>
<section class="section">
    <div class="container">
        <h1>Blood Donor Registrations</h1>
        <?php show_flash(); ?>
        
        <div class="tablewrap">
            <table class="table">
                <tr>
                    <th>Name</th>
                    <th>Blood</th>
                    <th>Phone</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php foreach($rows as $r): ?>
                <tr>
                    <td><?=e($r['name'])?></td>
                    <td><?=e($r['blood_group'])?></td>
                    <td><?=e($r['phone'])?></td>
                    <td><?=e($r['donation_date'])?></td>
                    <td><?=e($r['location'])?></td>
                    <td>
                        <b style="color:<?= $r['status'] === 'completed' ? '#4caf50' : '#ff9800' ?>;">
                            <?= strtoupper(e($r['status'])) ?>
                        </b>
                    </td>
                    <td>
                        <?php if (($r['status'] ?? 'registered') === 'registered'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="verify_donation">
                                <input type="hidden" name="donation_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn" style="background:#4caf50; color:#fff; padding:5px 10px; font-size:12px; border:none; border-radius:4px; cursor:pointer;">
                                    Verify ✓
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color:#4caf50; font-size:13px; font-weight:bold;">Approved</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
