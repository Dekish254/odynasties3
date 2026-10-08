<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config/config.php'; 
require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['gateway'] ?? '') !== 'mpesa') {
    header('Location: ' . base_url('payment-gateways.php'));
    exit;
}

$amount = (int)($_POST['amount'] ?? 0);
$phone  = trim($_POST['phone'] ?? '');
$userId = $_SESSION['member_user']['id'];

// Normalize mobile format to 254XXXXXXXXX
$phone = preg_replace('/[^0-9]/', '', $phone);
if (strpos($phone, '0') === 0) {
    $phone = '254' . substr($phone, 1);
} elseif (strpos($phone, '+') === 0) {
    $phone = substr($phone, 1);
}

if ($amount < 10 || strlen($phone) !== 12) {
    die("Error: Please provide a valid transaction amount (Min KES 10) and correct phone format (2547XXXXXXXX).");
}

// ==========================================
// 2. YOUR APPROVED LIVE PRODUCTION CREDENTIALS
// ==========================================
$consumerKey       = trim("bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc"); 
$consumerSecret    = trim("zL7NqOw5H3di8cEfGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv");
$businessShortCode = "6280635"; // Your live approved number
$passkey           = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1"; // Your production passkey

$timestamp = date('YmdHis');
$password  = base64_encode($businessShortCode . $passkey . $timestamp);

$callbackUrl = base_url('mpesa-callback.php'); 
$accountReference = "Odynasties";
$transactionDesc  = "Project Funding Support";

// ==========================================
// =============================================================
// 3. FIXED DIAGNOSTIC PRODUCTION OAUTH TOKEN FETCH (FORCED PRINT)
// =============================================================
$authUrl = "https://safaricom.co.ke";
$credentials = base64_encode(trim($consumerKey) . ":" . trim($consumerSecret));

$headers = [
    "Authorization: Basic " . $credentials,
    "Content-Type: application/json",
    "Accept: application/json"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $authUrl);
curl_setopt($ch, CURLOPT_PORT, 443);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
// Add this single line inside your cURL setup block to bypass the firewall:
curl_setopt($ch, CURLOPT_PROXY, "http://proxy-server.com:port");


$rawResponse = curl_exec($ch);

if ($rawResponse === false) {
    $curlError = curl_error($ch);
    curl_close($ch);
    die("<h3>Outbound Connection Failed</h3>" . htmlspecialchars($curlError));
}

curl_close($ch);

// FORCE RAW DUMP: This breaks past the token error text to show the exact server error array
if (empty($rawResponse) || strpos($rawResponse, 'access_token') === false) {
    die("<h3>Safaricom Raw Error Log Diagnostic</h3>" .
        "<strong>Raw Server Output:</strong> <pre>" . htmlspecialchars($rawResponse) . "</pre><br>" .
        "<strong>What to look for:</strong><br>" .
        "1. If it says <em>\"Invalid Credentials\"</em>, your keys are sandbox keys or copy-pasted wrong.<br>" .
        "2. If it is an HTML error page saying <em>\"403 Forbidden\"</em> or <em>\"Access Denied\"</em>, Safaricom's firewall is blocking your Railway IP range.");
}

$authResponse = json_decode($rawResponse, true);
$accessToken = $authResponse['access_token'] ?? null;


// ==========================================
// 4. FIRE PRODUCTION LIVE STK PUSH REQUEST
// ==========================================
$stkUrl = "https://safaricom.co.ke";

$curl_post_data = [
    'BusinessShortCode' => $businessShortCode,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerBuyGoodsOnline', // Change to 'CustomerPayBillOnline' if your shortcode is a Paybill
    'Amount'            => $amount,
    'PartyA'            => $phone,
    'PartyB'            => $businessShortCode,
    'PhoneNumber'       => $phone,
    'CallBackURL'       => $callbackUrl,
    'AccountReference'  => $accountReference,
    'TransactionDesc'   => $transactionDesc
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $stkUrl);
curl_setopt($ch, CURLOPT_PORT, 443);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$rawStkResponse = curl_exec($ch);
$response = json_decode($rawStkResponse, true);
curl_close($ch);

// ==========================================
// 5. UPDATE RAILWAY DATABASE INSTANCE
// ==========================================
if (($response['ResponseCode'] ?? '') === '0') {
    $merchantRequestId = $response['MerchantRequestID'];
    $checkoutRequestId = $response['CheckoutRequestID'];
    
    $stmt = $pdo->prepare("
        INSERT INTO project_donations (user_id, phone_number, amount, merchant_request_id, checkout_request_id, status) 
        VALUES (?, ?, ?, ?, ?, 'PENDING')
    ");
    $stmt->execute([$userId, $phone, $amount, $merchantRequestId, $checkoutRequestId]);
    
    echo "<script>alert('M-Pesa STK Push dispatched! Enter your PIN on your mobile phone to support the project.'); window.location.href='" . base_url('member-home.php') . "';</script>";
} else {
    die("<h3>M-Pesa STK Push Rejected</h3><pre>" . print_r($response, true) . "</pre>");
}
?>
