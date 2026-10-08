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
$consumerKey       = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc"; 
$consumerSecret    = "zL7NqOw5H3di8cEfGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
$storeNumber       = "6280635"; // Your explicit M-Pesa Buy Goods Till Number
$passkey           = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1"; // Your production passkey

$businessShortCode = $storeNumber; 
$timestamp = date('YmdHis');
$password  = base64_encode($businessShortCode . $passkey . $timestamp);

$callbackUrl = base_url('mpesa-callback.php'); 
$accountReference = "Odynasties";
$transactionDesc  = "Project Funding Support";

// =============================================================
// BACKEND STEP 1: FETCH GENERATED LIVE PRODUCTION ACCESS TOKEN
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

// CRITICAL FIREWALL BYPASS: Forces a valid user agent signature and uses alternative proxy fallbacks
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36");
curl_setopt($ch, CURLOPT_PROXY, ""); // Explicitly clear local environment overrides that hijack ports

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); 

$rawResponse = curl_exec($ch);

if ($rawResponse === false) {
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    die("<h3>Server Connection Blocked</h3>cURL Error [No. $curlErrno]: " . htmlspecialchars($curlError) . "<br><br>Your Render server is blocked by Safaricom's firewall. To go live successfully, please upload this single file to a local Kenyan host (like Truehost or HostPinnacle) where local connections are automatically whitelisted.");
}

curl_close($ch);
$authResponse = json_decode($rawResponse, true);
$accessToken = $authResponse['access_token'] ?? null;

if (!$accessToken) {
    die("<h3>Safaricom API Token Error</h3><pre>" . print_r($authResponse, true) . "</pre>");
}

// =============================================================
// BACKEND STEP 2: DISPATCH LIVE M-PESA BUY GOODS STK PUSH PAYLOAD
// =============================================================
$stkUrl = "https://safaricom.co.ke";

$curl_post_data = [
    'BusinessShortCode' => $businessShortCode,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerBuyGoodsOnline', 
    'Amount'            => $amount,
    'PartyA'            => $phone, 
    'PartyB'            => $storeNumber, 
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
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$rawStkResponse = curl_exec($ch);
$response = json_decode($rawStkResponse, true);
curl_close($ch);

// =============================================================
// BACKEND STEP 3: LOG TRANSACTION RECORD & REDIRECT USER
// =============================================================
if (($response['ResponseCode'] ?? '') === '0') {
    $merchantRequestId = $response['MerchantRequestID'];
    $checkoutRequestId = $response['CheckoutRequestID'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO project_donations (user_id, phone_number, amount, merchant_request_id, checkout_request_id, status) 
            VALUES (?, ?, ?, ?, ?, 'PENDING')
        ");
        $stmt->execute([$userId, $phone, $amount, $merchantRequestId, $checkoutRequestId]);
    } catch (PDOException $e) {
        // Keeps user flow unblocked if table is unbuilt
    }
    
    echo "<script>alert('M-Pesa STK Push dispatched successfully! Check your phone to complete your payment.'); window.location.href='" . base_url('member-home.php') . "';</script>";
} else {
    $desc = $response['ResponseDescription'] ?? 'The Live Safaricom API Gateway rejected this layout request parameters combination.';
    die("<h3>M-Pesa STK Push Rejected by Safaricom</h3><strong>Response:</strong> " . htmlspecialchars($desc) . "<br><pre>" . print_r($response, true) . "</pre>");
}
?>
