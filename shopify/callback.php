<?php 
require_once '../config.php';
require_once '../db.php';
require_once 'shopify_functions.php';

$params = $_GET;
if (!verifyHmac($params, SHOPIFY_API_SECRET)) die('Invalid HMAC');

// Validate nonce       
if ($_SESSION['nonce'] !== $_GET['state']) die('Invalid nonce');
    
$shop = $_GET['shop'];
$code = $_GET['code'];
// Shopify code for access token
$access_token = getAccessToken($shop, $code); 

if (isset($access_token)) {

    // Step 2: Fetch Store Details via shopify Rest API
    $shopDetailsResponse_json = getShopDetailsRestAPI($shop,$access_token); //return value in json
    $shopDetailsResponse = json_decode($shopDetailsResponse_json, true);
    if (!isset($shopDetailsResponse['shop'])) {
        die("Error: Failed to retrieve shop details.");
    }

    $shop                                   = $shopDetailsResponse['shop']['myshopify_domain'];
    $domain                                 = $shopDetailsResponse['shop']['domain'] ?? $shop;
    
    //Fetch Store logo details via shopify Graphql
    $logo_data = getShopLogo($shop, $access_token);
    if(isset($logo_data)){
        $logo_url = "https://".$domain."/cdn/shop/files/".str_replace('shopify://shop_images/', '', $logo_data);
    } else {
        $logo_url = ''; 
    }

    // Step 3: Define Data
    $shopify_id                             = $shopDetailsResponse['shop']['id'] ?? '';
    $store_name                             = $shopDetailsResponse['shop']['name'] ?? '';
    $shop_owner                             = $shopDetailsResponse['shop']['shop_owner'] ?? '';
    $email                                  = $shopDetailsResponse['shop']['email'] ?? '';
    $phone                                  = $shopDetailsResponse['shop']['phone'] ?? '';
    $plan_display_name                      = $shopDetailsResponse['shop']['plan_display_name'] ?? '';
    $plan_name                              = $shopDetailsResponse['shop']['plan_name'] ?? '';
    $country                                = $shopDetailsResponse['shop']['country'] ?? '';
    $currency                               = $shopDetailsResponse['shop']['currency'] ?? '';
    $timezone                               = $shopDetailsResponse['shop']['timezone'] ?? '';
    $iana_timezone                          = $shopDetailsResponse['shop']['iana_timezone'] ?? '';
    $country_code                           = $shopDetailsResponse['shop']['country_code'] ?? '';
    $country_name                           = $shopDetailsResponse['shop']['country_name'] ?? '';
    $address1                               = $shopDetailsResponse['shop']['address1'] ?? '';
    $address2                               = $shopDetailsResponse['shop']['address2'] ?? '';
    $city                                   = $shopDetailsResponse['shop']['city'] ?? '';
    $zip                                    = $shopDetailsResponse['shop']['zip'] ?? '';
    $province                               = $shopDetailsResponse['shop']['province'] ?? '';
    $province_code                          = $shopDetailsResponse['shop']['province_code'] ?? '';
    $primary_locale                         = $shopDetailsResponse['shop']['primary_locale'] ?? '';
    $money_format                           = $shopDetailsResponse['shop']['money_format'] ?? '';
    $money_with_currency_format             = $shopDetailsResponse['shop']['money_with_currency_format'] ?? '';
    $money_in_emails_format                 = $shopDetailsResponse['shop']['money_in_emails_format'] ?? '';
    $money_with_currency_in_emails_format   = $shopDetailsResponse['shop']['money_with_currency_in_emails_format'] ?? ''; 
    $restapi_json                           = $shopDetailsResponse_json;
    $created_at                             = $shopDetailsResponse['shop']['created_at'] ?? '';
    $updated_at                             = $shopDetailsResponse['shop']['updated_at'] ?? '';

    // Step 4: Insert or Update Store Information
    $query = "INSERT INTO stores 
    (shop, domain, access_token, shopify_id, store_name, shop_owner, logo_url, email, phone, 
        plan_display_name, plan_name, country, currency, timezone, iana_timezone, 
        country_code, country_name, address1, address2, city, zip, province, 
        province_code, primary_locale, money_format, money_with_currency_format, 
        money_in_emails_format, money_with_currency_in_emails_format, 
        restapi_json, created_at, updated_at, app_install_date) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
    domain = VALUES(domain),
    access_token = VALUES(access_token), 
    shopify_id = VALUES(shopify_id),
    store_name = VALUES(store_name), 
    shop_owner = VALUES(shop_owner),
    logo_url = VALUES(logo_url),
    email = VALUES(email), 
    phone = VALUES(phone), 
    plan_display_name = VALUES(plan_display_name),
    plan_name = VALUES(plan_name),
    country = VALUES(country), 
    currency = VALUES(currency), 
    timezone = VALUES(timezone), 
    iana_timezone = VALUES(iana_timezone), 
    country_code = VALUES(country_code), 
    country_name = VALUES(country_name), 
    address1 = VALUES(address1), 
    address2 = VALUES(address2),
    city = VALUES(city), 
    zip = VALUES(zip), 
    province = VALUES(province),
    province_code = VALUES(province_code),
    primary_locale = VALUES(primary_locale), 
    money_format = VALUES(money_format), 
    money_with_currency_format = VALUES(money_with_currency_format), 
    money_in_emails_format = VALUES(money_in_emails_format), 
    money_with_currency_in_emails_format = VALUES(money_with_currency_in_emails_format), 
    restapi_json = VALUES(restapi_json), 
    updated_at = NOW(),
    app_install_date = NOW(),
    id = LAST_INSERT_ID(id)";
    
    $conn = DB::getInstance();
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssssssssssssssssssssssssssss", 
        $shop, $domain, $access_token, $shopify_id, $store_name, $shop_owner, $logo_url, $email, $phone, 
        $plan_display_name, $plan_name, $country, $currency, $timezone, $iana_timezone, 
        $country_code, $country_name, $address1, $address2, $city, $zip, $province, 
        $province_code, $primary_locale, $money_format, $money_with_currency_format, 
        $money_in_emails_format, $money_with_currency_in_emails_format, 
        $restapi_json, $created_at, $updated_at
    );

    if (!$stmt->execute()) {
        die("SQL Error: " . $stmt->error);
    }
    $shop_id = $conn->insert_id;
    
    //create free subscription. 
    $stmt = $conn->prepare("SELECT id FROM store_subscriptions WHERE store_id = ? AND status = 'active'");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $subscription_result = $stmt->get_result();

    if ($subscription_result->num_rows < 1) {
         // Assuming the free plan is identified by the name 'Free'
        $plans_query = $conn->query("SELECT * FROM `plans` where price = '0.00'  ORDER BY id");
        $freePlan = $plans_query->fetch_assoc();
        
        // You might set start_date to the current datetime and end_date as needed (e.g., one year later).
        $start_date = date("Y-m-d H:i:s");
        // For demonstration, we set the end_date to one year from now.
        $end_date = date("Y-m-d H:i:s", strtotime("+1 year"));
        

        $insertSql = "INSERT INTO store_subscriptions 
    (store_id, plan_id, order_limit, email_limit, order_used, email_used, features, start_date, end_date, status)
    VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, 'active')";

        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param(
            "iiiisss",
            $shop_id,
            $freePlan['id'],
            $freePlan['order_limit'],
            $freePlan['email_limit'],
            $freePlan['features'],
            $start_date,
            $end_date
        );

        if (!$stmt->execute()) {
            die("SQL Error: " . $stmt->error);
        }

    }

    //Create Invoice Table.
    $shop_table_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop)); // Sanitize table name
    $invoice_table = "invoices_" . $shop_table_name;

    // ✅ Create Dynamic Invoice Table for the Store
    $create_table_query = "CREATE TABLE IF NOT EXISTS `$invoice_table` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `order_id` varchar(50) NOT NULL,  -- Changed from BIGINT to VARCHAR
        `order_number` varchar(64) DEFAULT NULL,
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
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        `payment_method` varchar(50) DEFAULT NULL,  -- Added payment method
        `order_status` ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
        `products` LONGTEXT DEFAULT NULL,
        `invoice_status` enum('pending','generated') DEFAULT 'pending',
        `email_status` enum('pending','sent') DEFAULT 'pending',
        `pdf_invoice` LONGTEXT DEFAULT NULL,
         UNIQUE KEY (`order_id`)  -- Ensuring order_id remains unique
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($create_table_query) === TRUE) {
       // echo "Subscription activated, and invoice table `$invoice_table` created successfully.";
    } else {
        echo "Error creating table: " . $conn->error;
    }
    // Fetch last 20 paid orders from Shopify
    $api_url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/orders.json?financial_status=paid&limit=20";
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
        // echo "Insert Successful for Order ID: $order_id <br>";
        }
    }

    // Create webhook

    if (registerShopifyWebhooks($shop, $access_token)) {
        $redirect_url = "https://{$shop}/admin/apps/" . SHOPIFY_API_KEY;
        header("Location: " . $redirect_url);
        exit;
    } else {
        //echo "Webhook registration failed.";
        die('Installation failed');
    }
    
} else {
    die('Installation failed');
}
?>