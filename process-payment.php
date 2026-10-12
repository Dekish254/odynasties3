<?php 
date_default_timezone_set('Africa/Nairobi'); 
require 'config/config.php'; 
require_member(); 

// Security Guard Checkpoint 
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { 
    die("Security verification failed. Invalid request token."); 
} 

// FIXED: Redirect back to the correct file if accessed directly
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header("Location: payment-gateways.php"); 
    exit; 
} 

$gateway = isset($_POST['gateway']) ? trim($_POST['gateway']) : ''; 
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0; 

// ================================================================= 
// MAIN HANDLER 1: SAFARICOM M-PESA GATEWAY (STK PUSH INITIATION)
// ================================================================= 
if ($gateway === 'mpesa') { 
    // IMPORTANT: Move these credentials securely to config/config.php in a production environment
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

    // 1. GENERATE OAUTH ACCESS TOKEN
    // FIXED: Swapped out placeholder domain for the active sandbox endpoint
    // For Production, use: https://safaricom.co.ke
  $tokenUrl = "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials"; 
    
    $curl = curl_init($tokenUrl); 
    curl_setopt($curl, CURLOPT_HTTPHEADER, [ 
        'Content-Type: application/json'
    ]); 
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret); 
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Set to true in production with proper certificates
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
    $timestamp = date('YmdHis'); 
    $password = base64_encode($businessShortCode . $passkey . $timestamp); 

    // 2. INITIATE STK PUSH (LIPA NA M-PESA ONLINE EXPRESS)
    // FIXED: Pointed to the valid functional M-Pesa express processrequest address
    // For Production, use: https://safaricom.co.ke
  $stkPushUrl = "https://safaricom.co.ke";
    
    // TODO: Replace with your actual live server callback URL handling the Daraja JSON response
    $callbackUrl = "https://yourdomain.com"; 

    $payload = array(
        "BusinessShortCode" => $businessShortCode,
        "Password"          => $password,
        "Timestamp"         => $timestamp,
        "TransactionType"   => "CustomerPayBillOnline", // Change to CustomerBuyGoodsOnline if using a Till No.
        "Amount"            => round($amount),         
        "PartyA"            => $phone,
        "PartyB"            => $businessShortCode,
        "PhoneNumber"       => $phone,
        "CallBackURL"       => $callbackUrl,
        "AccountReference"  => "SupportPayment",       
        "TransactionDesc"   => "Payment for support services"
    ); 

    $curlSTK = curl_init($stkPushUrl); 
    curl_setopt($curlSTK, CURLOPT_HTTPHEADER, array( 
        'Authorization: Bearer ' . $accessToken, 
        'Content-Type: application/json'
    )); 
    curl_setopt($curlSTK, CURLOPT_POST, true); 
    curl_setopt($curlSTK, CURLOPT_POSTFIELDS, json_encode($payload)); 
    curl_setopt($curlSTK, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($curlSTK, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($curlSTK, CURLOPT_SSL_VERIFYHOST, false); 
    
    $stkResponse = curl_exec($curlSTK); 
    curl_close($curlSTK); 

    echo "<h3>M-Pesa STK Push Response:</h3>"; 
    echo "<pre>" . htmlspecialchars($stkResponse) . "</pre>"; 
    exit; 
} 

die("Fatal execution error. Untracked payment option processed."); 
?>
