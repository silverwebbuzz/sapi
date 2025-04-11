<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once '../vendor/autoload.php';
 
// Verify webhook HMAC
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
$data = file_get_contents('php://input');
$calculated_hmac = base64_encode(hash_hmac('sha256', $data, SHOPIFY_API_SECRET, true));

if (!hash_equals($hmac, $calculated_hmac)) {
    http_response_code(401);
    die('Invalid webhook HMAC');
}

// Decode webhook data
$webhook = $order = json_decode($data, true);
$shop = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
$topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC']; // Get webhook topic
$cdate = date("Y-m-d H:i:s");

$conn = DB::getInstance();

// webhook logs.
$insertSql = "INSERT INTO webhook (shop, topic, orders, cdate) VALUES (?, ?, ?, ?)";
$webhook_id = DBHelper::insert($insertSql,"ssss",[$shop, $topic, $data, $cdate]);
// webhook logs end.

if ($topic === 'app/uninstalled') {
    // Handle App Uninstall
    $affectedRows = DBHelper::execute("UPDATE stores SET status = 'uninstalled' WHERE shop = ?","s",[$shop]);
    
} elseif ($topic === 'orders/create') {

    // Sanitize shop name to match table name
    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
    $invoice_table = "invoices_" . $shop_name;

    // Insert orders into the database
    $invoice_insert_query = "
    INSERT INTO `$invoice_table` 
    (order_id, order_number, order_name, customer_name, customer_email, billing_address, shipping_address, currency, subtotal_price, total_price, tax_amount, discount_amount, shipping_cost, invoice_status, email_status, payment_method, order_status, products) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
        order_name = VALUES(order_name),
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
    ";

    $order_id = $order['id']; 
    $order_number = $order['order_number'];
    $order_name = $order['name'];
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

    $webhook_id = DBHelper::insert($invoice_insert_query,"ssssssssdddddsss",[$order_id,
    $order_number,
    $order_name,
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
    $products]);

    $shop_data = DBHelper::selectOne(
        "SELECT id, shop_owner, status FROM stores WHERE `shop` = ? AND `status` = ?",
        "ss", 
        [$shop, "installed"]
    );
    $shop_id = $shop_data['id'];
    
    $generatepdf  = generatepdf($shop_id,$order_id);
    $sendemail  = sendemail($shop_id,$order_id);

} elseif ($topic === 'app_subscription/activated') {

    handleSubscriptionActivated($webhook);

} elseif ($topic === 'app_subscription/cancelled') {  

    handleSubscriptionCancelled($webhook);

} else {
    error_log("Unhandled webhook topic: {$topic}");
}

// Respond to Shopify
http_response_code(200);