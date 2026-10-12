<?php
require 'config/config.php'; 
require_member();

// Automatically initialize a secure token signature inside the member's browser session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$title = 'Odynasties — Support the Project';
require 'includes/header.php';
?>
<style>
.pay-container {max-width: 900px; margin: 0 auto; padding: 20px 0 60px;}
.pay-header {text-align: center; margin-bottom: 40px;}
.pay-header h1 {font-size: 32px; color: #182932; margin-bottom: 10px;}
.pay-header p {color: #52636c; font-size: 16px; max-width: 600px; margin: 0 auto; line-height: 1.6;}

/* Core Gateway Selection Grid layout */
.gateway-grid {display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-top: 30px;}
.gateway-card {background: #fff; border: 1px solid #e1e8eb; border-radius: 16px; padding: 28px; box-shadow: 0 8px 24px rgba(12,31,41,.04); display: flex; flex-direction: column; transition: transform 0.2s ease, border-color 0.2s ease;}
.gateway-card:hover {transform: translateY(-2px); border-color: #49b848;}

.gateway-badge {display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 4px; margin-bottom: 15px; align-self: flex-start;}
.badge-mpesa {background: #eef7f1; color: #49b848;}
.badge-intl {background: #f7fafc; color: #4a5568;}

.gateway-card h2 {margin: 0 0 10px 0; font-size: 22px; color: #182932;}
.gateway-card p {color: #52636c; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0; flex: 1;}

/* Input Form adjustments within gateway structures */
.amount-input-group {display: flex; gap: 10px; margin-bottom: 15px;}
.amount-input-group input {flex: 1; padding: 11px; border: 1px solid #ced6da; border-radius: 8px; font-size: 14px; outline: none; color: #182932;}
.amount-input-group input:focus {border-color: #49b848;}
.pay-submit-btn {width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: 700; font-size: 14px; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.2s ease;}

.btn-mpesa {background: #49b848; color: #fff;} 
.btn-mpesa:hover {background: #379a36;}
.btn-intl {background: #4a5568; color: #fff;} 
.btn-intl:hover {background: #2d3748;}

.help-text {display: block; font-size: 11px; color: #718096; margin-top: -10px; margin-bottom: 15px; padding-left: 2px;}

@media(max-width: 768px) {
  .gateway-grid {grid-template-columns: 1fr;}
}
</style>

<div class="container pay-container">
  <div class="pay-header">
    <h1>Support the Odynasties Project</h1>
    <p>Help us fund global infrastructure, continuous development, hosting profiles, and reliable emergency blood dispatch operations. Choose your preferred transactional provider channel below.</p>
  </div>

  <div class="gateway-grid">
    
    <!-- Option 1: Mobile Money Gateway (M-Pesa Integration) - RESTORED ACTIVE -->
    <div class="gateway-card" style="border-color: #49b848; box-shadow: 0 8px 24px rgba(73,184,72,.06);">
      <span class="gateway-badge badge-mpesa">Official Gateway</span>
      <h2>M-Pesa STK Push</h2>
      <p>Instant transactional processing using your mobile phone. Triggers an automated secure STK PIN request prompt window directly on your handset screen.</p>
      
      <!-- FIXED FORM ACTION: Targets process-payment.php (hyphenated) to clear 404 routing errors -->
      <form action="process-payment.php" method="POST" id="mpesaPaymentForm">
        <!-- Secure Anti-CSRF Parameters passed over context -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="gateway" value="mpesa">

        <div class="amount-input-group">
          <input 
            type="number" 
            name="amount" 
            placeholder="Amount (KES)" 
            min="1" 
            max="300000" 
            required
          >
          <input 
            type="tel" 
            name="phone" 
            placeholder="07XXXXXXXX" 
            pattern="^(?:254|\+254|0)?(7|1)[0-9]{8}$"
            title="Please enter a valid Kenyan number (e.g. 0712345678)" 
            required
          >
        </div>
        <span class="help-text">Accepts Safaricom 07XX... and 01XX... lines natively.</span>
        
        <button type="submit" class="pay-submit-btn btn-mpesa" id="mpesaSubmitBtn">Initiate STK Payment</button>
      </form>
    </div>

    <!-- Option 2: Secure Assistance / Custom Invoice Gateway -->
    <div class="gateway-card">
      <span class="gateway-badge badge-intl">Direct Queries</span>
      <h2>Alternative Payments</h2>
      <p>Need assistance or looking to support the project through alternative payment channels, custom invoices, corporate sponsorships, or direct wire transfers?</p>
      
      <div style="flex: 1;"></div> <!-- Spacer pushing action button uniformly -->
      <a href="contact.php?intent=custom_invoice" class="pay-submit-btn btn-intl">Contact Billing Desk</a>
    </div>

  </div>
</div>

<script>
    // Standard UI listener protecting backend from overlapping parallel payment requests
    document.getElementById('mpesaPaymentForm').addEventListener('submit', function() {
        const targetBtn = document.getElementById('mpesaSubmitBtn');
        targetBtn.disabled = true;
        targetBtn.innerText = 'Requesting PIN Prompt...';
        targetBtn.style.background = '#a0aec0';
        targetBtn.style.cursor = 'not-allowed';
    });
</script>

<?php require 'includes/footer.php'; ?>
