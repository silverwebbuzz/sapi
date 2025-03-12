<?php
require_once '..config.php';
require_once '..db.php';

// Verify webhook HMAC
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
$data = file_get_contents('php://input');
$calculated_hmac = base64_encode(hash_hmac('sha256', $data, SHOPIFY_API_SECRET, true));

/*if (!hash_equals($hmac, $calculated_hmac)) {
    http_response_code(401);
    die('Invalid webhook HMAC');
}*/

// Process order
$order = json_decode($data, true);
$conn = DB::getInstance();
$stmt1 = $conn->prepare("INSERT INTO invoice_test (`Param`)
                       VALUES (?)");

$stmt1->bind_param("ss",$data);
$stmt1->execute();

$stmt = $conn->prepare("INSERT INTO invoices (shop_domain, order_id, invoice_number, customer_email, amount, currency, status, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");

$invoice_number = 'INV-' . time() . '-' . $order['id'];
$stmt->bind_param("sissss",
    $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'],
    $order['id'],
    $invoice_number,
    $order['email'],
    $order['total_price'],
    $order['currency']
);
$stmt->execute();

http_response_code(200);