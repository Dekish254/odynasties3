<?php
require 'config/config.php'; // Contains user data hooks, database connections, and API constants
require_member();            // Access guard ensuring only authenticated members can trigger transactions

// 1. SECURITY VALIDATION: Confirm form matching parameters
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Security verification failed. Invalid request token.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: support.php");
    exit;
}

// 2. PARSE INCOMING DATA ATTRIBUTES
$gateway   = isset($_POST['gateway']) ? trim($_POST['gateway']) : '';
$amount    = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

// Fetch member tracking metrics from session variables
$userEmail = $_SESSION['user_email'] ?? 'member@odynasties.org'; 
$userName  = $_SESSION['user_name'] ?? 'Odynasties Member';

// Unique ledger tracking reference code for accounting records
$txRef = 'ODY-' . time() . '-' . rand(1000, 9999);

switch ($gateway) {
    
    // =================================================================
    // CHANNEL 1: SAFARICOM M-PESA GATEWAY (UNCHANGED INTEGRATION)
    // =================================================================
    case 'mpesa':
        // Safaricom parameters and configurations remain exactly as you structured them
        $consumerKey       = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc";
        $consumerSecret    = "zL7NqOw5H3di8ceFGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
        $businessShortCode = "6280635";
        $passkey           = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1";

        // Handle flexible incoming front-end phone inputs and sanitize to strict Kenyan formatting rules
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }

        // Token endpoint tracking
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

        // Target M-Pesa STK Query validation endpoint route
        $queryUrl = "https://safaricom.co.ke";
        $checkoutRequestID = "ws_CO_08102026150740123456"; // Use your actual checkout request ID variable here

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
        break;

    // =================================================================
    // CHANNEL 2: FLUTTERWAVE CREDIT & DEBIT CARD HANDLER
    // =================================================================
    case 'card':
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
            'Authorization: Bearer ' . FLW_SECRET_KEY, // Defined in your config.php
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
        break;

    // =================================================================
    // CHANNEL 3: PAYPAL DIGITAL WALLET CHECKOUT HANDLER
    // =================================================================
    case 'paypal':
        if ($amount access_token ?? null;
        if (!$paypalToken) { 
            die("PayPal Authentication Timeout. Could not establish handshake connection."); 
        }

        // Configure standard order processing parameters
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
            "Authorization: Bearer " . $paypalToken,
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
        break;

    default:
        die("Fatal execution error. Untracked payment option processed.");
}
