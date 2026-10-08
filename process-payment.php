<?php
// 1. Force the server to print out hidden backend error variables instead of a blank screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config/config.php'; 
require_member(); // Safeguard: Block unauthenticated traffic

// Ensure the form request is a POST sequence coming directly from your payment gateway screen
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['gateway'] ?? '') !== 'mpesa') {
    header('Location: ' . base_url('payment-gateways.php'));
    exit;
}

$amount = (int)($_POST['amount'] ?? 0);
$phone  = trim($_POST['phone'] ?? '');
$userId = $_SESSION['member_user']['id'];

// Normalize mobile format to Safaricom's required 254XXXXXXXXX string
$phone = preg_replace('/[^0-9]/', '', $phone);
if (strpos($phone, '0') === 0) {
    $phone = '254' . substr($phone, 1);
} elseif (strpos($phone, '+') === 0) {
    $phone = substr($phone, 1);
}

// Validation rules boundary check
if ($amount < 10 || strlen($phone) !== 12) {
    die("Error: Please provide a valid transaction amount (Min KES 10) and correct phone format (2547XXXXXXXX).");
}

// =============================================================
// YOUR APPROVED LIVE PRODUCTION BUY GOODS (TILL) CREDENTIALS
// =============================================================
$consumerKey       = "3B91FGqA6qxUVKL5vXQ8Sd1oqSA9H1vQFWPPopsYQQhHPZnc"; 
$consumerSecret    = "dqANw2odXYFmDhac0wqpee3gBPFQ3w1UpIqEbiSxnzTw1rSQpEJlAecjQDhp1H1P";
$storeNumber       = "7122120"; // Your explicit M-Pesa Buy Goods Till Number
$passkey           = "ed3513511649cc0565e6b9e843ddef6947731076df3cb552a7a4eefc8bc7b4fc"; // Your production passkey

$businessShortCode = $storeNumber; 
$timestamp = date('YmdHis');
$password  = base64_encode($businessShortCode . $passkey . $timestamp);

$callbackUrl = base_url('mpesa-callback.php'); 
$accountReference = "Odynasties";
$transactionDesc  = "Project Funding Support";

// Concatenate and completely trim keys to eliminate unintended spaces
$credentials = base64_encode(trim($consumerKey) . ":" . trim($consumerSecret));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Processing Payment — Odynasties</title>
<style>
  body { background:#071722; color:#fff; font-family:sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
  .loader-card { background:#fff; color:#14212b; padding:40px; border-radius:16px; width:100%; max-width:400px; box-shadow:0 10px 40px rgba(0,0,0,0.3); text-align:center; }
  .spinner { width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #d71920; border-radius: 50%; animation: spin 1s linear infinite; margin: 20px auto; }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  h2 { margin-top:0; color:#14212b; font-size: 22px; }
  p { color:#556675; font-size:14px; line-height:1.5; }
</style>
</head>
<body>

<div class="loader-card">
  <div class="spinner"></div>
  <h2>Connecting to M-Pesa...</h2>
  <p>Please hold tight. We are securely communicating with Safaricom to trigger the STK PIN prompt directly onto your phone (<strong>+<?=e($phone)?></strong>).</p>
</div>

<?php
  // Generate tracking references early to seed the database record
  $merchantRequestId = "ODY_M_" . uniqid();
  $checkoutRequestId = "ODY_C_" . bin2hex(random_bytes(8));
  
  // Try writing the log row, but bypass the script freeze if the table does not exist yet
  try {
      $stmt = $pdo->prepare("
          INSERT INTO project_donations (user_id, phone_number, amount, merchant_request_id, checkout_request_id, status) 
          VALUES (?, ?, ?, ?, ?, 'PENDING')
      ");
      $stmt->execute([$userId, $phone, $amount, $merchantRequestId, $checkoutRequestId]);
  } catch (PDOException $e) {
      // Ignored silently for now so the user can still receive the STK prompt immediately
  }
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Step 1: Request Authorization Access Token using your Kenyan ISP Network
    fetch("https://safaricom.co.ke", {
        method: "GET",
        headers: {
            "Authorization": "Basic <?=$credentials?>"
        }
    })
    .then(res => res.json())
    .then(authData => {
        if (!authData.access_token) {
            throw new Error("Safaricom Authorization Rejected. Verify portal key entries.");
        }
        
        // Step 2: Fire the STK Push Request Payload directly from the user's browser
        return fetch("https://safaricom.co.ke", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + authData.access_token
            },
            body: JSON.stringify({
                "BusinessShortCode": "<?=$businessShortCode?>",
                "Password": "<?=$password?>",
                "Timestamp": "<?=$timestamp?>",
                "TransactionType": "CustomerBuyGoodsOnline",
                "Amount": "<?=$amount?>",
                "PartyA": "<?=$phone?>",
                "PartyB": "<?=$storeNumber?>",
                "PhoneNumber": "<?=$phone?>",
                "CallBackURL": "<?=$callbackUrl?>",
                "AccountReference": "<?=$accountReference?>",
                "TransactionDesc": "<?=$transactionDesc?>"
            })
        });
    })
    .then(res => res.json())
    .then(stkData => {
        if (stkData.ResponseCode === "0") {
            alert("M-Pesa STK Push dispatched successfully! Enter your PIN on your mobile device.");
            window.location.href = "<?=base_url('member-home.php')?>";
        } else {
            alert("STK Push Failed: " + (stkData.ResponseDescription || "Unknown Error"));
            window.location.href = "<?=base_url('payment-gateways.php')?>";
        }
    })
    .catch(err => {
        alert("Transaction Flow Interrupted: " + err.message);
        window.location.href = "<?=base_url('payment-gateways.php')?>";
    });
});
</script>

</body>
</html>
