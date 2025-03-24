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
    $stmt = $conn->prepare("SELECT shop FROM stores WHERE id = ?");
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
        `order_id` bigint(20) NOT NULL,
        `customer_name` varchar(255) DEFAULT NULL,
        `customer_email` varchar(255) DEFAULT NULL,
        `billing_address` text DEFAULT NULL,
        `shipping_address` text DEFAULT NULL,
        `currency` varchar(10) DEFAULT NULL,
        `subtotal_price` decimal(10,2) DEFAULT NULL,
        `total_price` decimal(10,2) DEFAULT NULL,
        `tax_amount` decimal(10,2) DEFAULT NULL,
        `discount_amount` decimal(10,2) DEFAULT NULL,
        `shipping_cost` decimal(10,2) DEFAULT NULL,
        `invoice_status` enum('pending','generated') DEFAULT 'pending',
        `email_status` enum('pending','sent') DEFAULT 'pending',
        `created_at` timestamp NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($create_table_query) === TRUE) {
        echo "Subscription activated, and invoice table `$invoice_table` created successfully.";
    } else {
        echo "Error creating table: " . $conn->error;
    }

    // Fetch last 50 paid orders from Shopify
    $api_url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/orders.json?status=any&financial_status=paid&limit=50";
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Shopify-Access-Token: $access_token"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $orders = json_decode($response, true)['orders'] ?? [];

    if (!$orders) {
        die("No orders found.");
    }
    // Insert orders into the database
    $stmt = $conn->prepare("
        INSERT INTO `$invoice_table` (shop, order_id, customer_name, customer_email, billing_address, shipping_address, currency, subtotal_price, total_price, tax_amount, discount_amount, shipping_cost, invoice_status, email_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
        ON DUPLICATE KEY UPDATE order_id = order_id
    ");

    foreach ($orders as $order) {
        $order_id = $order['id'];
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

        $stmt->bind_param("sisssssdssss",
            $shopify_domain,
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
        $stmt->execute();
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