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
.gateway-card:hover {transform: translateY(-2px); border-color: #26a269;}

.gateway-badge {display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 4px; margin-bottom: 15px; align-self: flex-start;}
.badge-mpesa {background: #eef7f1; color: #26a269;}
.badge-intl {background: #fff6ee; color: #dd6b20;}

.gateway-card h2 {margin: 0 0 10px 0; font-size: 22px; color: #182932;}
.gateway-card p {color: #52636c; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0; flex: 1;}

/* Input Form adjustments within gateway structures */
.amount-input-group {display: flex; gap: 10px; margin-bottom: 15px;}
.amount-input-group input {flex: 1; padding: 11px; border: 1px solid #ced6da; border-radius: 8px; font-size: 14px; outline: none;}
.amount-input-group input:focus {border-color: #26a269;}
.pay-submit-btn {width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: 700; font-size: 14px; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.2s ease;}

.btn-mpesa {background: #26a269; color: #fff;} .btn-mpesa:hover {background: #1e8251;}
.btn-intl {background: #dd6b20; color: #fff;} .btn-intl:hover {background: #b85619;}

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
    
    <!-- Option 1: Mobile Money Gateway (M-Pesa Integration) -->
    <div class="gateway-card">
      <span class="gateway-badge badge-mpesa">Mobile Money</span>
      <h2>M-Pesa STK Push</h2>
      <p>Instant transactional processing using your mobile phone. Triggers an automated secure STK PIN request prompt window directly on your handset screen.</p>
      
      <form action="process-payment.php" method="post">
        <input type="hidden" name="gateway" value="mpesa">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="amount-input-group">
          <input type="number" name="amount" placeholder="Amount (KES)" required min="10">
          <input type="tel" name="phone" placeholder="e.g. 0712345678" required pattern="^(?:\+254|254|0)?(7|1)[0-9]{8}$" title="Please enter a valid Kenyan phone number (e.g. 0712345678 or 254712345678)">
        </div>
        <button type="submit" class="pay-submit-btn btn-mpesa">Pay via M-Pesa</button>
      </form>
    </div>

    <!-- Option 2: Alternative Crypto Gateway -->
    <div class="gateway-card">
      <span class="gateway-badge badge-intl">Alternative Currency</span>
      <h2>Crypto Network</h2>
      <p>Decentralized project tracking via digital asset ledgers. Generates standard public destination keys for safe routing across Bitcoin, Ethereum, and USDT channels.</p>
      
      <div class="amount-input-group">
        <input type="text" value="USDT (TRC20): TR7NHqjeKQxGTCi8q8DE4G6Z..." readonly style="background: #f7fafb; cursor: text; font-size:12px;">
      </div>
      <a href="contact.php?intent=crypto_invoice" class="pay-submit-btn btn-intl" style="background:#4a5568; color:#fff; text-align:center; text-decoration:none; display:block;">Request Invoice Wallet</a>
    </div>

  </div>
</div>

<?php require 'includes/footer.php'; ?>
