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
        updated_at = VALUES(updated_at)";

        $conn = DB::getInstance();
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssssssssssssssssssssssssss", 
        $shop, $domain, $access_token, $shopify_id, $store_name, $shop_owner, $logo_url, $email, $phone, 
        $plan_display_name, $plan_name, $country, $currency, $timezone, $iana_timezone, 
        $country_code, $country_name, $address1, $address2, $city, $zip, $province, 
        $province_code, $primary_locale, $money_format, $money_with_currency_format, 
        $money_in_emails_format, $money_with_currency_in_emails_format, 
        $restapi_json, $created_at, $updated_at
        );
        $stmt->execute();

    // Create webhook

    $webhook_url = SHOPIFY_APP_URL . '/shopify/webhook'; 
    $topics = [
        'orders/create',
        'orders/paid',
        'app/uninstalled'
    ];

    foreach ($topics as $topic) {
        $ch = curl_init("https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/webhooks.json");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Shopify-Access-Token: ' . $response['access_token']
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'webhook' => [
                    'topic' => $topic,
                    'address' => $webhook_url,
                    'format' => 'json'
                ]
            ]),
            CURLOPT_RETURNTRANSFER => true
        ]);

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status == 201) {
            error_log("Webhook for {$topic} registered successfully!");
        } else {
            error_log("Failed to register webhook for {$topic}. Response: " . $result);
            exit;
        }
    }

    $redirect_url = "https://{$shop}/admin/apps/" . SHOPIFY_API_KEY;
    header("Location: " . $redirect_url);
    exit;
} else {
    die('Installation failed');
}
?>