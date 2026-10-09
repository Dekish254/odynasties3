<?php
date_default_timezone_set('Africa/Nairobi');

// 1. PRODUCTION CREDENTIALS
$consumerKey    = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc";
$consumerSecret = "zL7NqOw5H3di8ceFGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
$businessShortCode = "6280635";
$passkey        = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1";

// 2. GENERATE ACCESS TOKEN WITH SSL & USER-AGENT FIXES
$tokenUrl = "https://safaricom.co.ke";

$curl = curl_init($tokenUrl);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)' // Simulates a clean browser header to bypass firewall blocks
]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);

// Crucial for some Linux servers lacking updated local root SSL certificates
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

$tokenResponse = curl_exec($curl);

// Check if cURL itself failed to connect
if (curl_errno($curl)) {
    die("cURL Network Error: " . curl_error($curl));
}

curl_close($curl);

$tokenResult = json_decode($tokenResponse);

// If the json object is null, print out the raw string returned by Safaricom to identify the precise system block
if ($tokenResult === null || !isset($tokenResult->access_token)) {
    echo "<h3>Raw Server Response from Safaricom:</h3>";
    echo "<pre>" . htmlspecialchars($tokenResponse) . "</pre>";
    die("Token Generation Failed. Read the raw response above to see the issue.");
}

$accessToken = $tokenResult->access_token;

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
