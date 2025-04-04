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
$order = json_decode($data, true);
$shop = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
$topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC']; // Get webhook topic
$cdate = date("Y-m-d H:i:s");

$conn = DB::getInstance();

// webhook logs.
$insertSql = "INSERT INTO webhook (shop, topic, orders, cdate) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);
// Bind parameters: 'shop' and 'topic' are strings, 'orders' is a string, and 'cdate' is a string representation of datetime.
$stmt->bind_param("ssss", $shop, $topic, $data, $cdate);
if (!$stmt->execute()) {
    die("SQL Error: " . $stmt->error);
}
// webhook logs end.

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

    // Insert orders into the database
    $stmt = $conn->prepare("
    INSERT INTO `$invoice_table` 
    (order_id, order_number, customer_name, customer_email, billing_address, shipping_address, currency, subtotal_price, total_price, tax_amount, discount_amount, shipping_cost, invoice_status, email_status, payment_method, order_status, products) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
        customer_name = VALUES(customer_name),
        customer_email = VALUES(customer_email),
        billing_address = VALUES(billing_address),
        shipping_address = VALUES(shipping_address),
        currency = VALUES(currency),
        subtotal_price = VALUES(subtotal_price),
        total_price = VALUES(total_price),
        tax_amount = VALUES(tax_amount),
        discount_amount = VALUES(discount_amount),
        shipping_cost = VALUES(shipping_cost),
        invoice_status = VALUES(invoice_status),
        email_status = VALUES(email_status),
        payment_method = VALUES(payment_method),
        order_status = VALUES(order_status),
        products = VALUES(products)
    ");

    $order_id = $order['id']; 
    $order_number = $order['order_number'];
    $customer_name = $order['customer']['first_name'] . ' ' . $order['customer']['last_name'];
    $customer_email = $order['customer']['email'];
    $currency = $order['currency'];
    $subtotal_price = $order['subtotal_price'];
    $total_price = $order['total_price'];
    $tax_amount = isset($order['total_tax']) ? $order['total_tax'] : 0.00;
    $discount_amount = isset($order['total_discounts']) ? $order['total_discounts'] : 0.00;
    $shipping_cost = isset($order['total_shipping_price_set']['shop_money']['amount']) ? $order['total_shipping_price_set']['shop_money']['amount'] : 0.00;
    $billing_address = json_encode($order['billing_address'] ?? []);
    $shipping_address = json_encode($order['shipping_address'] ?? []);
    $payment_method = $order['gateway'] ?? 'Unknown';
    $order_status = $order['financial_status'] ?? 'pending';
    $products = json_encode($order['line_items'], JSON_UNESCAPED_UNICODE);


    $stmt->bind_param("sssssssdddddsss", 
    $order_id,
    $order_number,
    $customer_name, 
    $customer_email, 
    $billing_address, 
    $shipping_address, 
    $currency, 
    $subtotal_price, 
    $total_price, 
    $tax_amount, 
    $discount_amount, 
    $shipping_cost,
    $payment_method,
    $order_status,
    $products
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