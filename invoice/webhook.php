<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'shopify_functions.php';
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
    // orders/create often does not include a finalized payment/transaction.
    // Prefer the official array field when present.
    $payment_method = 'Unknown';
    if (!empty($order['payment_gateway_names']) && is_array($order['payment_gateway_names'])) {
        $payment_method = implode(', ', $order['payment_gateway_names']);
    }
    $order_status = $order['financial_status'] ?? 'pending';
    $products = json_encode($order['line_items'], JSON_UNESCAPED_UNICODE);

    $webhook_invoice_id = DBHelper::insert($invoice_insert_query,"ssssssssdddddsss",[$order_id,
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
        "SELECT id, shop_owner, status, auto_invoice_customer, auto_invoice_personal FROM stores WHERE `shop` = ? AND `status` = ?",
        "ss", 
        [$shop, "installed"]
    );
    $shop_id = $shop_data['id'];
    
    $sql_currentPlan = "SELECT * FROM store_subscriptions ss WHERE ss.store_id = ? AND ss.status = 'active'  ORDER BY ss.activated_on DESC LIMIT 1 ";
    $currentPlan = DBHelper::selectOne($sql_currentPlan, "i", [$shop_id]);

    if ($currentPlan['price']!='0.00') {
        // Generate PDF if within limits
        if ($currentPlan['order_used'] <= $currentPlan['order_limit']) {
            $generatepdf = generatepdf($shop_id,$order_id);
            
            // Send email to customer if enabled
            if ($shop_data['auto_invoice_customer']=='Yes' && $currentPlan['email_used'] <= $currentPlan['email_limit']) {
                $sendemail = sendemail($shop_id,$order_id);
            }
            
            // Send personal copy if enabled (independent of customer email setting)
            if ($shop_data['auto_invoice_personal']=='Yes' && $currentPlan['email_used'] <= $currentPlan['email_limit']) {
                $sendemail = sendemail($shop_id,$order_id, true);
            }
        }
    }

} elseif ($topic === 'orders/paid') {
    // When the order is paid, payment method + transaction details are more likely to be available.
    $order_id = $order['id'] ?? null;
    if ($order_id) {
        $shop_data = DBHelper::selectOne(
            "SELECT id, access_token, status FROM stores WHERE `shop` = ? LIMIT 1",
            "s",
            [$shop]
        );

        if ($shop_data && $shop_data['status'] === 'installed') {
            $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
            $invoice_table = "invoices_" . $shop_name;

            $payment_method = 'Unknown';
            if (!empty($order['payment_gateway_names']) && is_array($order['payment_gateway_names'])) {
                $payment_method = implode(', ', $order['payment_gateway_names']);
            }

            // If still unknown, try transactions API (best-effort)
            if ($payment_method === 'Unknown' && !empty($shop_data['access_token'])) {
                $transactions = getOrderTransactionsRestAPI($shop, $shop_data['access_token'], $order_id);
                if (is_array($transactions) && !empty($transactions)) {
                    // pick a reasonable transaction (sale/capture/authorization)
                    foreach ($transactions as $tx) {
                        $kind = strtolower($tx['kind'] ?? '');
                        if (in_array($kind, ['sale', 'capture', 'authorization'], true)) {
                            if (!empty($tx['gateway'])) {
                                $payment_method = $tx['gateway'];
                                break;
                            }
                        }
                    }
                    // fallback to first tx gateway
                    if ($payment_method === 'Unknown' && !empty($transactions[0]['gateway'])) {
                        $payment_method = $transactions[0]['gateway'];
                    }
                }
            }

            $order_status = $order['financial_status'] ?? 'paid';

            DBHelper::execute(
                "UPDATE `$invoice_table` SET payment_method = ?, order_status = ? WHERE order_id = ?",
                "sss",
                [$payment_method, $order_status, (string)$order_id]
            );
        }
    }

} elseif ($topic === 'app_subscriptions/update') {

    try {
        $subscription = $webhook['app_subscription'] ?? null;
        if (!$subscription) {
            throw new Exception('Missing app_subscription in payload');
        }

        $shopId = extractIdFromGql($subscription['admin_graphql_api_shop_id'] ?? '');
        if (!$shopId) {
            throw new Exception('Missing shop id in payload');
        }

        // Hand the FULL payload to the persister — Shopify gives us everything
        // we need (name, status, price, interval, created_at) directly, so we
        // don't need a second GraphQL call that can race with propagation.
        store_app_subscriptions($shopId, $subscription);

    } catch (\Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] app_subscriptions/update FAILED: "
            . $e->getMessage()
            . " | payload=" . substr($data, 0, 1000)
            . "\n", 3, __DIR__ . '/webhook_debug.log');
    }
}

// Respond to Shopify
http_response_code(200);
exit();