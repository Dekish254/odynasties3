<?php
// 1. Clear hidden background blocks and force diagnostics output
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Secure cross-origin parameters setup to keep your main Render front-end anchored
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// 2. Establish a direct secure connection to your live active database on Railway
$host     = "junction.proxy.rlwy.net";
$port     = "10375";
$user     = "root";
$password = "OGPjARzHlyssppysTWVWvrsYCszLHRHy";
$dbname   = "odynasties";

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true
    ]);
} catch (PDOException $e) {
    die(json_encode(["ResponseCode" => "1", "ResponseDescription" => "Railway Cloud connection breakdown: " . $e->getMessage()]));
}

// 3. Process the M-Pesa incoming attributes context parameters
$amount = (int)($_POST['amount'] ?? 0);
$phone  = trim($_POST['phone'] ?? '');
$userId = (int)($_POST['user_id'] ?? 1); // Passes user contextual state identifiers cleanly

$phone = preg_replace('/[^0-9]/', '', $phone);
if (strpos($phone, '0') === 0) { $phone = '254' . substr($phone, 1); }

if ($amount < 10 || strlen($phone) !== 12) {
    echo json_encode(["ResponseCode" => "1", "ResponseDescription" => "Provide a valid amount (Min KES 10) and correct format."]);
    exit;
}

// Approved Live Production App Credentials
$consumerKey       = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc"; 
$consumerSecret    = "zL7NqOw5H3di8cEfGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
$storeNumber       = "6280635"; 
$passkey           = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1"; 

// 4. Request Bearer Token (Will pass instantly because your hosting is local to Kenya)
$authUrl = "https://safaricom.co.ke";
$credentials = base64_encode($consumerKey . ":" . $consumerSecret);

$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_PORT, 443);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . $credentials,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$authResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

$accessToken = $authResponse['access_token'] ?? null;
if (!$accessToken) {
    echo json_encode(["ResponseCode" => "1", "ResponseDescription" => "Token authorization failed. Check app profile setup on portal."]);
    exit;
}

// 5. Build cryptographically signed values and fire the Production STK Push
$timestamp = date('YmdHis');
$password  = base64_encode($storeNumber . $passkey . $timestamp);
$stkUrl    = "https://safaricom.co.ke";

$curl_post_data = [
    'BusinessShortCode' => $storeNumber,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerBuyGoodsOnline', // Managed strictly for Store Tills
    'Amount'            => $amount,
    'PartyA'            => $phone,
    'PartyB'            => $storeNumber,
    'PhoneNumber'       => $phone,
    'CallBackURL'       => "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/mpesa-callback.php",
    'AccountReference'  => "Odynasties",
    'TransactionDesc'   => "Project Funding Support"
];

$ch = curl_init($stkUrl);
curl_setopt($ch, CURLOPT_PORT, 443);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$stkResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

// 6. Log transaction record directly into your live Railway cloud instance
if (($stkResponse['ResponseCode'] ?? '') === '0') {
    $merchantId = $stkResponse['MerchantRequestID'];
    $checkoutId = $stkResponse['CheckoutRequestID'];
    
    $stmt = $pdo->prepare("INSERT INTO project_donations (user_id, phone_number, amount, merchant_request_id, checkout_request_id, status) VALUES (?, ?, ?, ?, ?, 'PENDING')");
    $stmt->execute([$userId, $phone, $amount, $merchantId, $checkoutId]);
}

echo json_encode($stkResponse);
?>
