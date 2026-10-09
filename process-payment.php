<?php
date_default_timezone_set('Africa/Nairobi');
require 'config/config.php'; 
require_member();            

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Security verification failed. Invalid request token.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: support.php");
    exit;
}

$gateway = isset($_POST['gateway']) ? trim($_POST['gateway']) : '';
$amount  = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

$userEmail = $_SESSION['user_email'] ?? 'member@odynasties.org'; 
$userName  = $_SESSION['user_name'] ?? 'Odynasties Member';
$txRef     = 'ODY-' . time() . '-' . rand(1000, 9999);

// =================================================================
// CHANNEL 1: SAFARICOM M-PESA GATEWAY (UNCHANGED INTEGRATION)
// =================================================================
if ($gateway === 'mpesa') {
    $consumerKey       = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc";
    $consumerSecret    = "zL7NqOw5H3di8ceFGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
    $businessShortCode = "6280635";
    $passkey           = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1";

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
    $checkoutRequestID = "ws_CO_08102026150740123456"; 

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

// =================================================================
// CHANNEL 2: FLUTTERWAVE CREDIT & DEBIT CARD HANDLER
// =================================================================
if ($gateway === 'card') {
    if ($amount < 5) { 
        die("Minimum Card transaction amount is $5 USD."); 
    }
    
    $endpoint = "https://flutterwave.com";
    $cardPayload = [
        "tx_ref" => $txRef,
        "amount" => $amount,
        "currency" => "USD",
        "redirect_url" => "https://" . $_SERVER['HTTP_HOST'] . "/payment-callback.php?gateway=flutterwave",
        "customer" => [
            "email" => $userEmail,
            "name" => $userName
        ],
        "customizations" => [
            "title" => "Odynasties Project Support",
            "description" => "Global Infrastructure & Emergency Blood Dispatch Funding"
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . FLW_SECRET_KEY, 
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cardPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response);
    if ($result && $result->status === 'success') {
        header("Location: " . $result->data->link);
        exit;
    } else {
        die("Flutterwave Processing Error: " . ($result->message ?? 'Gateway timeout. Please try again.'));
    }
}

// =================================================================
// CHANNEL 3: PAYPAL DIGITAL WALLET CHECKOUT HANDLER
// =================================================================
if ($gateway === 'paypal') {
    if ($amount < 5) { 
        die("Minimum PayPal transaction amount is $5 USD."); 
    }
    
    $authUrl = "https://paypal.com";
    $authCh = curl_init($authUrl);
    curl_setopt($authCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($authCh, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET); 
    curl_setopt($authCh, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($authCh, CURLOPT_SSL_VERIFYPEER, false);
    
    $authResponse = json_decode(curl_exec($authCh));
    curl_close($authCh);
    
    // Using associative array reference to completely eliminate 'access_token' object identification parse issues
    $payKey = isset($authResponse->access_token) ? $authResponse->access_token : null;
    if (!$payKey) { 
        die("PayPal Authentication Timeout. Could not establish handshake connection."); 
    }

    $orderUrl = "https://paypal.com";
    $orderData = [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "reference_id" => $txRef,
            "amount" => [
                "currency_code" => "USD",
                "value" => number_format($amount, 2, '.', '')
            ],
            "description" => "Odynasties Project Infrastructure Support"
        ]],
        "application_context" => [
            "return_url" => "https://" . $_SERVER['HTTP_HOST'] . "/payment-callback.php?gateway=paypal&status=success",
            "cancel_url" => "https://" . $_SERVER['HTTP_HOST'] . "/support.php"
        ]
    ];

    $ch = curl_init($orderUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $payKey,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $orderResponse = json_decode(curl_exec($ch));
    curl_close($ch);

    if (isset($orderResponse->links)) {
        foreach ($orderResponse->links as $link) {
            if ($link->rel === 'approve') {
                header("Location: " . $link->href);
                exit;
            }
        }
    }
    die("PayPal Order Routing Failure. System halted.");
}

die("Fatal execution error. Untracked payment option processed.");
?>
