<?php
// Secure background script to handle live Safaricom receipt transmissions
header("Content-Type: application/json");

$callbackJSONData = file_get_contents('php://input');
$data = json_decode($callbackJSONData, true);

if (isset($data['Body']['stkCallback'])) {
    $callback = $data['Body']['stkCallback'];
    $resultCode = $callback['ResultCode'];
    $merchantId = $callback['MerchantRequestID'];
    $checkoutId = $callback['CheckoutRequestID'];
    
    // Connect directly to your live Railway schema database index
    $host = "junction.proxy.rlwy.net"; $port = "10375"; $user = "root"; $password = "OGPjARzHlyssppysTWVWvrsYCszLHRHy"; $dbname = "odynasties";
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    if ($resultCode == 0) {
        // Payment approved! Extract receipt number parameters
        $items = $callback['CallbackMetadata']['Item'];
        $receiptNumber = '';
        foreach ($items as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') {
                $receiptNumber = $item['Value'];
                break;
            }
        }
        
        // Update database rows to SUCCESS state parameters context markers
        $stmt = $pdo->prepare("UPDATE project_donations SET mpesa_receipt_number = ?, status = 'SUCCESS' WHERE merchant_request_id = ? AND checkout_request_id = ?");
        $stmt->execute([$receiptNumber, $merchantId, $checkoutId]);
    } else {
        // Transaction abandoned or cancelled by the user
        $stmt = $pdo->prepare("UPDATE project_donations SET status = 'FAILED' WHERE merchant_request_id = ? AND checkout_request_id = ?");
        $stmt->execute([$merchantId, $checkoutId]);
    }
}

echo json_encode(["ResultCode" => 0, "ResultDesc" => "Callback Processed Successfully"]);
?>
