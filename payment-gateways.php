<?php
require 'config/config.php'; 
require_member();

// Initialize secure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$title = 'Odynasties — Support the Project';
require 'includes/header.php';
?>
<!-- Keep your existing styles here -->

<div class="container pay-container">
  <div class="pay-header">
    <h1>Support the Odynasties Project</h1>
    <p>Choose your preferred transactional provider channel below.</p>
  </div>

  <div class="gateway-grid">
    
    <!-- Option 1: Mobile Money Gateway -->
    <div class="gateway-card" style="border-color: #49b848;">
      <span class="gateway-badge" style="background: #eef7f1; color: #49b848;">Official Gateway</span>
      <h2>M-Pesa STK Push</h2>
      <p>Instant transactional processing using your mobile phone handset.</p>
      
      <!-- FIXED: Form now routes directly to your actual processing script -->
      <form action="process-payment.php" method="POST" id="mpesaPaymentForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="gateway" value="mpesa">

        <div class="amount-input-group">
          <input type="number" name="amount" placeholder="Amount (KES)" min="1" max="300000" required>
          <input type="tel" name="phone" placeholder="07XXXXXXXX" pattern="^(?:254|\+254|0)?(7|1)[0-9]{8}$" required>
        </div>
        
        <button type="submit" class="pay-submit-btn" style="background:#49b848; color:#fff;" id="mpesaSubmitBtn">Initiate STK Payment</button>
      </form>
    </div>

    <!-- Option 2: Alternative Support Queries -->
    <div class="gateway-card">
      <span class="gateway-badge" style="background:#f7fafc; color:#4a5568;">Direct Queries</span>
      <h2>Alternative Payments</h2>
      <p>Need assistance with corporate sponsorships, custom billing invoices, or direct wire transfers?</p>
      <a href="contact.php?intent=custom_invoice" class="pay-submit-btn" style="background:#4a5568; color:#fff; text-decoration:none; display:block; text-align:center;">Contact Billing Desk</a>
    </div>

  </div>
</div>

<script>
    document.getElementById('mpesaPaymentForm').addEventListener('submit', function() {
        const targetBtn = document.getElementById('mpesaSubmitBtn');
        targetBtn.disabled = true;
        targetBtn.innerText = 'Requesting PIN Prompt...';
        targetBtn.style.background = '#a0aec0';
    });
</script>

<?php require 'includes/footer.php'; ?>
