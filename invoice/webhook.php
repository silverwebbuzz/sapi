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
    $payment_method = $order['gateway'] ?? 'Unknown';
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
        "SELECT id, shop_owner, status FROM stores WHERE `shop` = ? AND `status` = ?",
        "ss", 
        [$shop, "installed"]
    );
    $shop_id = $shop_data['id'];
    
    $generatepdf  = generatepdf($shop_id,$order_id);
    $sendemail  = sendemail($shop_id,$order_id);

} elseif ($topic === 'app_subscriptions/update') {  

    try {
        $subscription = $webhook['app_subscription'];
        $chargeId = extractIdFromGql($subscription['admin_graphql_api_id']);  // Returns 34950971692
        $shopId = extractIdFromGql($subscription['admin_graphql_api_shop_id']);  // Returns 92496724
        
        // Get store data in shopify table its save as 	shopify_id
        $store = DBHelper::selectOne("SELECT id, shop, access_token FROM stores WHERE shopify_id = ?  LIMIT 1", "i", [$shopId]);
        
        if (!$store) {
            throw new Exception("Store not found");
        }
        
        // Get FULL subscription details via GraphQL
        $subscriptionData = fetchSubscriptionWithGraphQL($store['shop'],$store['access_token'],$chargeId);
        
        if (!$subscriptionData) {
            throw new Exception("Failed to fetch subscription details");
        }

        // Determine plan limits based on your requirements
        $limits = calculatePlanLimits($subscriptionData['name'], $subscriptionData['price'], $subscriptionData['billing_interval']);

        // Clean up optional fields if not set
        $trialEndsOn = !empty($subscriptionData['trial_ends_on']) ? $subscriptionData['trial_ends_on'] : null;
        $cappedAmount = !empty($subscriptionData['capped_amount']) ? $subscriptionData['capped_amount'] : null;
        $terms = !empty($subscriptionData['terms']) ? $subscriptionData['terms'] : null;

        // Insert new subscription
        // Insert new subscription
        $newId = DBHelper::insert("
            INSERT INTO store_subscriptions (
                store_id, shopify_id, charge_id,
                plan_name, status, price, currency, billing_interval,
                interval_count, capped_amount, terms,
                activated_on, current_period_end, trial_ends_on, billing_on,
                order_limit, email_limit, order_used, email_used,
                is_test
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", "iisssssssssssssiiiii", [
            $store['id'],                             // store_id
            $shopId,                                  // shopify_id
            $subscriptionData['id'],                  // charge_id
            $subscriptionData['name'],                // plan_name
            strtolower($subscriptionData['status']),  // status
            $subscriptionData['price'],               // price
            $subscriptionData['currency'],            // currency
            $subscriptionData['billing_interval'],    // billing_interval
            $subscriptionData['interval_count'],      // interval_count
            $cappedAmount,                            // capped_amount
            $terms,                                   // terms
            $subscriptionData['activated_on'],        // activated_on
            $subscriptionData['current_period_end'],  // current_period_end
            $trialEndsOn,                             // trial_ends_on
            null,                                     // billing_on
            $limits['order_limit'],                   // order_limit
            $limits['email_limit'],                   // email_limit
            0,                                        // order_used
            0,                                        // email_used
            $subscriptionData['is_test']              // is_test
        ]);
        
        // 8. Cancel old subscriptions (except the one we just created)
        DBHelper::execute("
            UPDATE store_subscriptions 
            SET status = 'cancelled',
                cancelled_on = NOW(),
                updated_at = NOW()
            WHERE shopify_id = ? 
              AND id != ?
              AND status = 'active'
        ", "ii", [$shopId, $newId]);

        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'subscription_id' => $newId,
            'limits' => $limits
        ]);
        
    } catch (Exception $e) {
        http_response_code(200);
        error_log("Subscription processing failed: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// Respond to Shopify
http_response_code(200);
exit();