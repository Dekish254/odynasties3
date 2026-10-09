<?php
// 1. PRODUCTION CREDENTIALS (From your Daraja App Profile)
$consumerKey    = "bFuQg4fqHajr7VrG1umNX1XR63Y565AJM5Vs0sjGDcXbzphc";
$consumerSecret = "zL7NqOw5H3di8ceFGkNXoGvr4MAzaFiwnDsFc7SCRsiEGQX2r6QZaWrv4PL2GNiv";
$businessShortCode = "6280635";
$passkey        = "75fc730afea19a3765dffb3465daa94fa1cb19668476ed2acefad1045a57c3a1";

// 2. GENERATE ACCESS TOKEN (Hitting api.safaricom.co.ke)
$tokenUrl = "https://safaricom.co.ke";

$curl = curl_init($tokenUrl);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);

$tokenResponse = curl_exec($curl);
$tokenResult   = json_decode($tokenResponse);
$accessToken   = $tokenResult->access_token;

curl_close($curl);

if (!$accessToken) {
    die("Token Generation Failed. Check your production keys.");
}

// 3. GENERATE PRODUCTION PASSWORD & TIMESTAMP
$timestamp = date('YmdHis');
$password  = base64_encode($businessShortCode . $passkey . $timestamp);

// 4. PREPARE THE STK QUERY PAYLOAD
$queryUrl = "https://safaricom.co.ke";

// Replace this with the real CheckoutRequestID returned from your initial STK Push request
$checkoutRequestID = "ws_CO_08102026150740123456"; 

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
    'Content-Type: application/json'
));
curl_setopt($curlQuery, CURLOPT_POST, true);
curl_setopt($curlQuery, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($curlQuery, CURLOPT_RETURNTRANSFER, true);

$queryResponse = curl_exec($curlQuery);
curl_close($curlQuery);

// 6. OUTPUT THE API RESPONSE
echo $queryResponse;
?>
