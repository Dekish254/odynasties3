<?php
date_default_timezone_set('Africa/Nairobi');
require 'config/config.php'; 
require_member();            

// Security Guard Checkpoint
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Security verification failed. Invalid request token.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: support.php");
    exit;
}

$gateway = isset($_POST['gateway']) ? trim($_POST['gateway']) : '';
$amount  = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

// =================================================================
// MAIN HANDLER 1: SAFARICOM M-PESA GATEWAY (UNCHANGED INTEGRATION)
// =================================================================
if ($gateway === 'mpesa') {
    $consumerKey       = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc";
    $consumerSecret    = "zL7NqOw5H3di8ceFGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
    $businessShortCode = "6280635";
    $passkey           = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1";

    // Reformat phone format neatly to match strict Safaricom expectations (2547...)
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }

    $tokenUrl = "https://safaricom.co.ke";

    $curl = curl_init($tokenUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 15);

    $tokenResponse = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); 
    curl_close($curl);

    $tokenResult = json_decode($tokenResponse);
    if ($httpCode !== 200 || !isset($tokenResult->access_token)) {
        echo "<h3>Server Diagnosis:</h3>";
        echo "HTTP Status Code Received: <b>" . $httpCode . "</b><br>";
        echo "Raw response from Safaricom: <pre>" . htmlspecialchars($tokenResponse) . "</pre>";
        die("Stopping execution because no access token was generated.");
    }

    $accessToken = $tokenResult->access_token;
    $timestamp   = date('YmdHis');
    $password    = base64_encode($businessShortCode . $passkey . $timestamp);

    $queryUrl = "https://safaricom.co.ke";
    $checkoutRequestID = "ws_CO_08102026150740123456"; // Swap dynamically with your actual checkout request ID variable

    $payload = array(
        "BusinessShortCode" => $businessShortCode,
        "Password"          => $password,
        "Timestamp"         => $timestamp,
        "CheckoutRequestID" => $checkoutRequestID
    );

    $curlQuery = curl_init($queryUrl);
    curl_setopt($curlQuery, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ));
    curl_setopt($curlQuery, CURLOPT_POST, true);
    curl_setopt($curlQuery, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($curlQuery, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlQuery, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curlQuery, CURLOPT_SSL_VERIFYHOST, false);

    $queryResponse = curl_exec($curlQuery);
    curl_close($curlQuery);

    echo "<h3>M-Pesa STK Query Response:</h3>";
    echo "<pre>" . htmlspecialchars($queryResponse) . "</pre>";
    exit;
}

die("Fatal execution error. Untracked payment option processed.");
?>
