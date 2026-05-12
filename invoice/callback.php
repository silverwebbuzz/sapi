<?php
// ============ ERROR LOGGING ============
// Captures fatals, exceptions, and warnings to invoice/callback_debug.log
// so future failures are diagnosable. Does NOT echo anything to the browser
// (echoing before session_start would break the OAuth flow).
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/callback_debug.log');
error_reporting(E_ALL);

function callback_log($label, $data = null) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $label;
    if ($data !== null) {
        $line .= ' :: ' . (is_scalar($data) ? var_export($data, true) : json_encode($data, JSON_UNESCAPED_SLASHES));
    }
    error_log($line . "\n", 3, __DIR__ . '/callback_debug.log');
}

set_error_handler(function ($severity, $message, $file, $line) {
    callback_log('PHP_ERROR', ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line]);
    return false;
});
set_exception_handler(function ($e) {
    callback_log('UNCAUGHT_EXCEPTION', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        callback_log('FATAL_SHUTDOWN', $err);
    }
});
// =======================================

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';

$params = $_GET;
if (!verifyHmac($params, SHOPIFY_API_SECRET)) {
    callback_log('HMAC_FAILED', $_GET);
    die('Invalid HMAC');
}

// Validate nonce
if (!isset($_SESSION['nonce']) || $_SESSION['nonce'] !== $_GET['state']) {
    callback_log('NONCE_FAILED', ['session_nonce' => $_SESSION['nonce'] ?? null, 'state' => $_GET['state'] ?? null]);
    die('Invalid nonce');
}

$shop = $_GET['shop'];
$code = $_GET['code'];

// Shopify code for access token
$access_token = getAccessToken($shop, $code);

if (!isset($access_token)) {
    callback_log('ACCESS_TOKEN_EMPTY', ['shop' => $shop]);
    die('Installation failed');
}

$_SESSION['shop'] = $shop;
$_SESSION['access_token'] = $access_token;

// Step 2: Fetch Store Details via shopify Rest API
$shopDetailsResponse_json = getShopDetailsRestAPI($shop, $access_token);
$shopDetailsResponse = json_decode($shopDetailsResponse_json, true);
if (!isset($shopDetailsResponse['shop'])) {
    callback_log('SHOP_DETAILS_MISSING', ['shop' => $shop, 'raw' => substr((string)$shopDetailsResponse_json, 0, 500)]);
    die("Error: Failed to retrieve shop details.");
}

$shop   = $shopDetailsResponse['shop']['myshopify_domain'];
$domain = $shopDetailsResponse['shop']['domain'] ?? $shop;

// Fetch Store logo details via shopify Graphql
$logo_data = getShopLogo($shop, $access_token);
if (is_string($logo_data) && $logo_data !== '') {
    $logo_url = "https://" . $domain . "/cdn/shop/files/" . str_replace('shopify://shop_images/', '', $logo_data);
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
$created_at = date('Y-m-d H:i:s', strtotime($created_at));
$updated_at = date('Y-m-d H:i:s', strtotime($updated_at));

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
status = 'installed',
updated_at = NOW(),
app_install_date = NOW(),
id = LAST_INSERT_ID(id)";

$shop_id = DBHelper::insert($query, "sssssssssssssssssssssssssssssss",
    [$shop, $domain, $access_token, $shopify_id, $store_name, $shop_owner, $logo_url, $email, $phone,
    $plan_display_name, $plan_name, $country, $currency, $timezone, $iana_timezone,
    $country_code, $country_name, $address1, $address2, $city, $zip, $province,
    $province_code, $primary_locale, $money_format, $money_with_currency_format,
    $money_in_emails_format, $money_with_currency_in_emails_format,
    $restapi_json, $created_at, $updated_at]
);

// Create the free "Lifetime Free" subscription row, but ONLY if this store
// has no subscription history yet. Re-running the OAuth callback (e.g. after
// a reinstall or scope change) must NOT add a second free row alongside an
// existing paid plan.
$existing = DBHelper::selectOne(
    "SELECT id FROM store_subscriptions WHERE store_id = ? LIMIT 1",
    "i",
    [$shop_id]
);
if (!$existing) {
    DBHelper::insert(
        "INSERT INTO store_subscriptions (store_id, shopify_id) VALUES (?, ?)",
        "is",
        [$shop_id, (string)$shopify_id]
    );
}

// Create Invoice Table.
$shop_table_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
$invoice_table = "invoices_" . $shop_table_name;

$create_table_query = "CREATE TABLE IF NOT EXISTS `$invoice_table` (
    `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` varchar(50) NOT NULL,
    `order_number` varchar(32) DEFAULT NULL,
    `order_name` varchar(64) DEFAULT NULL,
    `customer_name` varchar(255) DEFAULT NULL,
    `customer_email` varchar(255) DEFAULT NULL,
    `billing_address` LONGTEXT DEFAULT NULL,
    `shipping_address` LONGTEXT DEFAULT NULL,
    `currency` varchar(10) DEFAULT NULL,
    `subtotal_price` decimal(10,2) DEFAULT NULL,
    `total_price` decimal(10,2) DEFAULT NULL,
    `tax_amount` decimal(10,2) DEFAULT NULL,
    `discount_amount` decimal(10,2) DEFAULT NULL,
    `shipping_cost` decimal(10,2) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `payment_method` varchar(50) DEFAULT NULL,
    `order_status` ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    `products` LONGTEXT DEFAULT NULL,
    `invoice_status` enum('pending','generated') DEFAULT 'pending',
    `email_status` enum('pending','sent') DEFAULT 'pending',
    `pdf_invoice` LONGTEXT DEFAULT NULL,
    `packing_slip_pdf` LONGTEXT DEFAULT NULL,
    `packing_slip_status` ENUM('pending','generated') NOT NULL DEFAULT 'pending',
     UNIQUE KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

DBHelper::createTable($create_table_query);

// Fetch last 100 paid orders from Shopify
$api_url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/orders.json?financial_status=paid&limit=100";
$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-Shopify-Access-Token: $access_token"
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);
if ($http_code != 200) {
    callback_log('ORDERS_API_FAILED', ['http' => $http_code, 'curl_err' => $curl_err, 'body' => substr((string)$response, 0, 500)]);
    die("Shopify API error: HTTP Code $http_code - Response: " . $response);
}

$orders = json_decode($response, true)['orders'] ?? [];

// Insert orders into the database
$invoice_qry = "
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

foreach ($orders as $order) {
    try {
        $order_id = $order['id'];
        $order_number = $order['order_number'];
        $order_name = $order['name'];
        $customer_name = isset($order['customer']) ? trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')) : '';
        $customer_email = $order['customer']['email'] ?? '';
        $currency = $order['currency'];
        $subtotal_price = $order['subtotal_price'];
        $total_price = $order['total_price'];
        $tax_amount = isset($order['total_tax']) ? $order['total_tax'] : 0.00;
        $discount_amount = isset($order['total_discounts']) ? $order['total_discounts'] : 0.00;
        $shipping_cost = isset($order['total_shipping_price_set']['shop_money']['amount']) ? $order['total_shipping_price_set']['shop_money']['amount'] : 0.00;
        $billing_address = json_encode($order['billing_address'] ?? []);
        $shipping_address = json_encode($order['shipping_address'] ?? []);
        $payment_method = 'Unknown';
        if (!empty($order['payment_gateway_names']) && is_array($order['payment_gateway_names'])) {
            $payment_method = implode(', ', $order['payment_gateway_names']);
        }
        $order_status = $order['financial_status'] ?? 'pending';
        $products = json_encode($order['line_items'], JSON_UNESCAPED_UNICODE);

        DBHelper::insert($invoice_qry, "ssssssssdddddsss",
            [$order_id, $order_number, $order_name, $customer_name, $customer_email,
            $billing_address, $shipping_address, $currency, $subtotal_price, $total_price,
            $tax_amount, $discount_amount, $shipping_cost, $payment_method, $order_status, $products]
        );
    } catch (\Throwable $e) {
        // One bad order shouldn't abort the whole install — log and continue.
        callback_log('ORDER_INSERT_FAILED', ['order_id' => $order['id'] ?? null, 'msg' => $e->getMessage(), 'line' => $e->getLine()]);
    }
}

// Create webhook
$shopify_webhook = registerShopifyWebhooks($shop, $access_token);

if ($shopify_webhook == 1) {
    $redirect_url = "https://{$shop}/admin/apps/" . SHOPIFY_API_KEY;
    header("Location: " . $redirect_url);
    exit;
} else {
    callback_log('WEBHOOK_REGISTRATION_FAILED', ['shop' => $shop]);
    die('Installation failed');
}
