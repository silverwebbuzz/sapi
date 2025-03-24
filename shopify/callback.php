<?php 
require_once '../config.php';
require_once '../db.php';
require_once 'shopify_functions.php';

$params = $_GET;
if (!verifyHmac($params, SHOPIFY_API_SECRET)) die('Invalid HMAC');

// Validate nonce       
if ($_SESSION['nonce'] !== $_GET['state']) die('Invalid nonce');

// Exchange code for access token
$shop = $_GET['shop'];
$ch = curl_init("https://{$shop}/admin/oauth/access_token");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'client_id' => SHOPIFY_API_KEY,
        'client_secret' => SHOPIFY_API_SECRET,
        'code' => $_GET['code']
    ]
]);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($response['access_token'])) {

    $access_token = $response['access_token'];
    // Step 2: Fetch Store Details
        $shopDetailsUrl = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/shop.json";
        $ch = curl_init($shopDetailsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "X-Shopify-Access-Token: $access_token"
            ]
        ]);
        $shopDetailsResponse = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!isset($shopDetailsResponse['shop'])) {
            die("Error: Failed to retrieve shop details.");
        }
        
        // Step 3: Extract Data
        $shop          = $shopDetailsResponse['shop']['myshopify_domain'];
        $store_name    = $shopDetailsResponse['shop']['name'] ?? '';
        $store_domain  = $shopDetailsResponse['shop']['domain'] ?? $shop;
        $email         = $shopDetailsResponse['shop']['email'] ?? '';
        $phone         = $shopDetailsResponse['shop']['phone'] ?? '';
        $current_plan  = $shopDetailsResponse['shop']['plan_name'] ?? '';
        $country       = $shopDetailsResponse['shop']['country'] ?? '';
        $currency      = $shopDetailsResponse['shop']['currency'] ?? '';
        $timezone      = $shopDetailsResponse['shop']['timezone'] ?? '';
        $iana_timezone = $shopDetailsResponse['shop']['iana_timezone'] ?? '';
        $country_code  = $shopDetailsResponse['shop']['country_code'] ?? '';
        $country_name  = $shopDetailsResponse['shop']['country_name'] ?? '';
        $created_at    = $shopDetailsResponse['shop']['created_at'] ?? '';
        $updated_at    = $shopDetailsResponse['shop']['updated_at'] ?? '';

        // Step 4: Insert or Update Store Information
        $query = "INSERT INTO stores 
          (shop, store_name, store_domain, access_token, email, phone, current_plan, country, currency, timezone, iana_timezone, country_code, country_name, created_at, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE 
          store_name = VALUES(store_name), 
          store_domain = VALUES(store_domain), 
          access_token = VALUES(access_token), 
          email = VALUES(email), 
          phone = VALUES(phone), 
          current_plan = VALUES(current_plan), 
          country = VALUES(country), 
          currency = VALUES(currency), 
          timezone = VALUES(timezone), 
          iana_timezone = VALUES(iana_timezone), 
          country_code = VALUES(country_code), 
          country_name = VALUES(country_name), 
          updated_at = NOW()";

    $conn = DB::getInstance();
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssssssssssss", $shop, $store_name, $store_domain, $access_token, $email, $phone, $current_plan, $country, $currency, $timezone, $iana_timezone, $country_code, $country_name, $created_at, $updated_at);
    $stmt->execute();

    // Create webhook
    $webhook_url = SHOPIFY_APP_URL . '/shopify/webhook';
    $ch = curl_init("https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/webhooks.json");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $response['access_token']
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'webhook' => [
                'topic' => 'orders/create',
                'address' => $webhook_url,
                'format' => 'json'
            ]
        ])
    ]);

    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Add validation
    if ($status === 201) {
        $webhook_data = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($webhook_data['webhook'])) {
            error_log("Webhook created: " . print_r($webhook_data, true));
        } else {
            error_log("Invalid JSON response: " . $result);
        }
    } else {
        error_log("Webhook creation failed: " . $result);
    }

    $redirect_url = "https://{$shop}/admin/apps/" . SHOPIFY_API_KEY;
    header("Location: " . $redirect_url);
    exit;
} else {
    die('Installation failed');
}
?>