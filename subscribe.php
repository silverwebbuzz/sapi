<?php
require_once 'config.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Invalid request method.");
}

// Retrieve GET parameters
$shop_id = $_GET['shop_id'];
$plan_id = $_GET['plan_id'];

$conn = DB::getInstance();

// Check if store already has an active subscription
$stmt = $conn->prepare("SELECT id FROM store_subscriptions WHERE store_id = ? AND status = 'active'");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$subscription_result = $stmt->get_result();

if ($subscription_result->num_rows > 0) {
    // Store is already subscribed
    header("Location: dashboard.php?shop_id=$shop_id");
    exit();
} else {
    // Fetch store details
    $stmt = $conn->prepare("SELECT shop,access_token FROM stores WHERE id = ?");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Invalid store.");
    }

    $store = $result->fetch_assoc();
    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop'])); // Sanitize table name
    $invoice_table = "invoices_" . $shop_name;

    // ✅ Create Dynamic Invoice Table for the Store
    $create_table_query = "CREATE TABLE IF NOT EXISTS `$invoice_table` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `order_id` varchar(50) NOT NULL,  -- Changed from BIGINT to VARCHAR
        `order_number` varchar(16) DEFAULT NULL,
        `customer_name` varchar(255) DEFAULT NULL,
        `customer_email` varchar(255) DEFAULT NULL,
        `billing_address` LONGTEXT DEFAULT NULL,  -- Changed to LONGTEXT for larger JSON data
        `shipping_address` LONGTEXT DEFAULT NULL, -- Changed to LONGTEXT
        `currency` varchar(10) DEFAULT NULL,
        `subtotal_price` decimal(10,2) DEFAULT NULL,
        `total_price` decimal(10,2) DEFAULT NULL,
        `tax_amount` decimal(10,2) DEFAULT NULL,
        `discount_amount` decimal(10,2) DEFAULT NULL,
        `shipping_cost` decimal(10,2) DEFAULT NULL,
        `invoice_status` enum('pending','generated') DEFAULT 'pending',
        `email_status` enum('pending','sent') DEFAULT 'pending',
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        `payment_method` varchar(50) DEFAULT NULL,  -- Added payment method
        `order_status` ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
        `products` LONGTEXT DEFAULT NULL 
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($create_table_query) === TRUE) {
       // echo "Subscription activated, and invoice table `$invoice_table` created successfully.";
    } else {
        echo "Error creating table: " . $conn->error;
    }

    $access_token = $store['access_token'];
    $shop = $store['shop'];
    // Fetch last 50 paid orders from Shopify
    $api_url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/orders.json?financial_status=paid&limit=50";
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: $access_token"
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != 200) {
        die("Shopify API error: HTTP Code $http_code - Response: " . $response);
    }

    $orders = json_decode($response, true)['orders'] ?? [];
    
    // Insert orders into the database
    $stmt = $conn->prepare("
    INSERT INTO `$invoice_table` 
    (order_id, order_number, customer_name, customer_email, billing_address, shipping_address, currency, subtotal_price, total_price, tax_amount, discount_amount, shipping_cost, invoice_status, email_status, payment_method, order_status, products) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?) 
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

    if (!$stmt) {
        die("Query Preparation Failed: " . $conn->error);
    }
    foreach ($orders as $order) {
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

        if (!$stmt->execute()) {
            die("Query Execution Failed: " . $stmt->error);
        } else {
            echo "Insert Successful for Order ID: $order_id <br>";
        }
    }

    echo "Orders inserted successfully!";


    // Subscribe the store (7-day free trial)
    $stmt = $conn->prepare("
        INSERT INTO store_subscriptions (store_id, plan_id, start_date, end_date, status) 
        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active')
    ");
    $stmt->bind_param("ii", $shop_id, $plan_id);


    if ($stmt->execute()) {
        // Redirect to dashboard after successful subscription
        header("Location: dashboard?shop_id=$shop_id");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>