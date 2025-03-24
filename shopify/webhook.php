<?php
require_once '../config.php';
require_once '../db.php';

// Verify webhook HMAC
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
$data = file_get_contents('php://input');
$calculated_hmac = base64_encode(hash_hmac('sha256', $data, SHOPIFY_API_SECRET, true));

if (!hash_equals($hmac, $calculated_hmac)) {
    http_response_code(401);
    die('Invalid webhook HMAC');
}

// Decode webhook data
$payload = json_decode($data, true);
$shop = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
$topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC']; // Get webhook topic

$conn = DB::getInstance();

if ($topic === 'app/uninstalled') {
    // Handle App Uninstall
    $stmt = $conn->prepare("UPDATE stores SET status = 'uninstalled' WHERE shop = ?");
    $stmt->bind_param("s", $shop);
    if ($stmt->execute()) {
        error_log("Store {$shop} marked as uninstalled.");
    } else {
        error_log("Failed to update store status: " . $stmt->error);
    }
} elseif ($topic === 'orders/create') {

    // Sanitize shop name to match table name
    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
    $invoice_table = "invoices_" . $shop_name;

    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$invoice_table'");
    if ($table_check->num_rows === 0) {
        die("Invoice table not found for store.");
    }

    // Handle Order Creation
    $order_id = $payload['id'];
    $customer_name = $payload['customer']['first_name'] . ' ' . $payload['customer']['last_name'];
    $customer_email = $payload['customer']['email'];
    $currency = $payload['currency'];
    $subtotal_price = $payload['subtotal_price'];
    $total_price = $payload['total_price'];
    $tax_amount = isset($payload['total_tax']) ? $payload['total_tax'] : 0.00;
    $discount_amount = isset($payload['total_discounts']) ? $payload['total_discounts'] : 0.00;
    $shipping_cost = isset($payload['total_shipping_price_set']['shop_money']['amount']) ? $payload['total_shipping_price_set']['shop_money']['amount'] : 0.00;
    
    $billing_address = json_encode($payload['billing_address'] ?? []);
    $shipping_address = json_encode($payload['shipping_address'] ?? []);

    // Store order in invoices table
    $stmt = $conn->prepare("
        INSERT INTO `$invoice_table` (shop, order_id, customer_name, customer_email, billing_address, shipping_address, currency, subtotal_price, total_price, tax_amount, discount_amount, shipping_cost, invoice_status, email_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
        ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            customer_email = VALUES(customer_email),
            billing_address = VALUES(billing_address),
            shipping_address = VALUES(shipping_address),
            subtotal_price = VALUES(subtotal_price),
            total_price = VALUES(total_price),
            tax_amount = VALUES(tax_amount),
            discount_amount = VALUES(discount_amount),
            shipping_cost = VALUES(shipping_cost),
            invoice_status = 'pending',
            email_status = 'pending';
    ");

    $stmt->bind_param("sisssssdssss",
        $shop,
        $order_id,
        $customer_name,
        $customer_email,
        $billing_address,
        $shipping_address,
        $currency,
        $subtotal_price,
        $total_price,
        $tax_amount,
        $discount_amount,
        $shipping_cost
    );

    if ($stmt->execute()) {
        error_log("Invoice created for Order ID: {$order_id}.");
    } else {
        error_log("Error inserting invoice: " . $stmt->error);
    }
} else {
    error_log("Unhandled webhook topic: {$topic}");
}

// Respond to Shopify
http_response_code(200);