<?php
date_default_timezone_set('Africa/Nairobi');

// 1. PRODUCTION CREDENTIALS
$consumerKey    = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc";
$consumerSecret = "zL7NqOw5H3di8ceFGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
$businessShortCode = "6280635";
$passkey        = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1";

// 2. GENERATE ACCESS TOKEN WITH STRICT CONNECTION BYPASSES
$tokenUrl = "https://safaricom.co.ke";

$curl = curl_init($tokenUrl);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: */*',
    'Connection: keep-alive'
]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);

// Connection Handshake Fixes
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);     // Don't reuse cached dead sockets
curl_setopt($curl, CURLOPT_TIMEOUT, 15);             // Don't hang indefinitely
curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Force IPv4 routing

$tokenResponse = curl_exec($curl);


// 3. GENERATE PASSWORD & TIMESTAMP
$timestamp = date('YmdHis');
$password  = base64_encode($businessShortCode . $passkey . $timestamp);

// 4. PREPARE THE STK QUERY PAYLOAD
$queryUrl = "https://safaricom.co.ke";
$checkoutRequestID = "ws_CO_08102026150740123456"; // Use your actual checkout request ID

$payload = array(
    "BusinessShortCode" => $businessShortCode,
    "Password"          => $password,
    "Timestamp"         => $timestamp,
    "CheckoutRequestID" => $checkoutRequestID
);

// 5. EXECUTE THE POST QUERY REQUEST
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

// 6. OUTPUT THE FINAL M-PESA RESPONSE
echo $queryResponse;
?>
