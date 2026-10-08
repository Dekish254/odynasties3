<?php
// Include your master architecture configuration file (handles Railway PDO database connection)
require 'config/config.php'; 
require_member(); // Safeguard: Block unauthenticated visitors

// Ensure the form request is a POST sequence coming directly from your payment gateway screen
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['gateway'] ?? '') !== 'mpesa') {
    header('Location: ' . base_url('payment-gateways.php'));
    exit;
}

$amount = (int)($_POST['amount'] ?? 0);
$phone  = trim($_POST['phone'] ?? '');
$userId = $_SESSION['member_user']['id'];

// 1. Sanitize Mobile Format Structure to conform strictly to Safaricom's 254XXXXXXXXX requirement
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

// 2. Safaricom M-Pesa Sandbox Configurations (Swap these strings out when going to live production)
$consumerKey       = "YOUR_M_PESA_CONSUMER_KEY"; 
$consumerSecret    = "YOUR_M_PESA_CONSUMER_SECRET";
$businessShortCode = "174379"; // Safaricom Sandbox testing Till/Paybill number
$passkey           = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919"; // Sandbox Passkey

// Generate a cryptographically valid M-Pesa programmatic security timestamp string
$timestamp = date('YmdHis');
$password  = base64_encode($businessShortCode . $passkey . $timestamp);

// Your web server's background receiver callback target link path
$callbackUrl = base_url('mpesa-callback.php'); 
$accountReference = "Odynasties";
$transactionDesc  = "Project Funding Support";

// 3. Request a secure Bearer Authorization Token via cURL from Safaricom's cloud servers
$authUrl = "https://safaricom.co.ke";
$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$authResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

$accessToken = $authResponse['access_token'] ?? null;

if (!$accessToken) {
    die("Error: Failed to fetch secure authentication token from M-Pesa API endpoint. Double-check Consumer Keys.");
}

// 4. Populate Payloads & Fire STK Push Command to Safaricom
$stkUrl = "https://safaricom.co.ke";

$curl_post_data = [
    'BusinessShortCode' => $businessShortCode,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerPayBillOnline',
    'Amount'            => $amount,
    'PartyA'            => $phone,
    'PartyB'            => $businessShortCode,
    'PhoneNumber'       => $phone,
    'CallBackURL'       => $callbackUrl,
    'AccountReference'  => $accountReference,
    'TransactionDesc'   => $transactionDesc
];

$ch = curl_init($stkUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// 5. Evaluate response parameters and commit transaction markers straight into your Railway instance
if (($response['ResponseCode'] ?? '') === '0') {
    $merchantRequestId = $response['MerchantRequestID'];
    $checkoutRequestId = $response['CheckoutRequestID'];
    
    // Save record to your live Railway cloud ledger table in a PENDING state
    $stmt = $pdo->prepare("
        INSERT INTO project_donations (user_id, phone_number, amount, merchant_request_id, checkout_request_id, status) 
        VALUES (?, ?, ?, ?, ?, 'PENDING')
    ");
    $stmt->execute([$userId, $phone, $amount, $merchantRequestId, $checkoutRequestId]);
    
    // Output a direct frontend verification response alert
    echo "<script>alert('M-Pesa STK Push dispatched! Enter your PIN on your mobile device to complete payment.'); window.location.href='" . base_url('member-home.php') . "';</script>";
} else {
    $desc = $response['ResponseDescription'] ?? 'Unknown Gateway Failure Error';
    echo "<script>alert('STK Push Error: " . addslashes($desc) . "'); window.location.href='" . base_url('payment-gateways.php') . "';</script>";
}
?>
