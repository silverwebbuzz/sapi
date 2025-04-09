<?php 
function setEncryptCookie($cookieData){
    $encryptedCookie = encryptCookie($cookieData);
    setcookie(
        'swb_auth',
        $encryptedCookie,
        [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'domain' => '.silverwebbuzz.com', // Allow subdomains
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None' // Required for Shopify iframe
        ]
    );
}
/**
 * Encrypt cookie data (AES-256-CBC)
 */
function encryptCookie($data) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt(
        json_encode($data),
        'AES-256-CBC',
        COOKIE_KEY,
        0,
        $iv
    );
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt cookie data
 */
function decryptCookie($cookie) {
    $data = base64_decode($cookie);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $decrypted = openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        COOKIE_KEY,
        0,
        $iv
    );
    return json_decode($decrypted, true);
}
/**
 * Handles direct access to the root URL
 */
function handleDirectAccess() {
    $shopifyLoginUrl = 'https://www.shopify.com/login';
    $shopParam = isset($_GET['shop']) ? htmlspecialchars($_GET['shop'], ENT_QUOTES, 'UTF-8') : SHOPIFY_APP_HANDLE;
    $installUrl = LIVE_SHOPIFY_APP_URL . '?shop=' . urlencode($shopParam);
    
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Install App</title>
</head>
<body>
    <h1>Please install this app via Shopify Admin</h1>
    <p>If you're a store owner, please <a href="$shopifyLoginUrl" target="_blank">login to Shopify</a> 
    and install this app from your admin dashboard.</p>
    <p>If you're being redirected from Shopify Admin, <a href="$installUrl">click here</a> to continue installation.</p>
</body>
</html>
HTML;
    exit;
}

/**
 * Handles OAuth request from Shopify Admin
 */
function handleOAuthRequest() {
    // Validate HMAC
    if (!validateHmac($_GET)) {
        die('Invalid HMAC');
    }

    $shop = $_GET['shop'];
    
    // Initial install - redirect to Shopify OAuth
    $install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, SHOPIFY_APP_URL . '/shopify/callback');
    header("Location: $install_url");
    exit();

}
/**
 * Validates HMAC signature from Shopify
 */
function validateHmac($params) {
    $hmac = $params['hmac'];
    unset($params['hmac']);
    
    ksort($params);
    $computedHmac = hash_hmac('sha256', http_build_query($params), SHOPIFY_API_SECRET);
    
    return hash_equals($hmac, $computedHmac);
}

// Verify HMAC
function verifyHmac(array $params) {
    if (empty($params['hmac'])) {
        error_log("HMAC parameter missing");
        return false;
    }

    $hmac = $params['hmac'];
    unset($params['hmac']);
    
    // Debug: Log received parameters
    error_log("Received Params: " . print_r($params, true));
    
    ksort($params);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    
    // Debug: Log generated query string
    error_log("Generated Query: " . $query);
    
    $calculated_hmac = hash_hmac('sha256', $query, SHOPIFY_API_SECRET);
    
    // Debug: Log both HMACs
    error_log("Received HMAC: " . $hmac);
    error_log("Calculated HMAC: " . $calculated_hmac);
    
    return hash_equals($hmac, $calculated_hmac);
}
// Verify shop domain format
function validateShopDomain($shop) {
    return preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop);
}

// Generate installation redirect URL
function getInstallUrl($shop, $scopes, $redirectUrl) {
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['nonce'] = $nonce;
    
    return "https://{$shop}/admin/oauth/authorize?" . http_build_query([
        'client_id' => SHOPIFY_API_KEY,
        'scope' => $scopes,
        'redirect_uri' => $redirectUrl,
        'state' => $nonce
    ]);
}

//Get Access Token from Shopify
function getAccessToken($shop,$code){
    $ch = curl_init("https://{$shop}/admin/oauth/access_token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'client_id' => SHOPIFY_API_KEY,
            'client_secret' => SHOPIFY_API_SECRET,
            'code' => $code
        ]
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $access_token = $response['access_token'];
}

//Get Shop all detials from shopify through rest API
function getShopDetailsRestAPI($shop,$access_token){
    $shopDetailsUrl = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/shop.json";
    $ch = curl_init($shopDetailsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: $access_token"
        ]
    ]);
    $shopDetailsResponse_json = curl_exec($ch);
    curl_close($ch);
    return $shopDetailsResponse_json;
}

function getShopLogo($shop, $access_token) {
    $url = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes.json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Shopify-Access-Token: ' . $access_token,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $themes = json_decode($response, true);
    
    // Get the active theme ID
    $active_theme_id = null;
    foreach ($themes['themes'] as $theme) {
        if ($theme['role'] === 'main') {
            $active_theme_id = $theme['id'];
            break;
        }
    }

    if (!$active_theme_id) {
        return null; // No active theme found
    }

    // Now fetch the theme assets (logo might be stored in settings_data.json)
    $url = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes/$active_theme_id/assets.json?asset[key]=config/settings_data.json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Shopify-Access-Token: ' . $access_token,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $assets = json_decode($response, true);
    
    if (isset($assets['asset']['value'])) {
        $settings_data = json_decode($assets['asset']['value'], true);
        if (isset($settings_data['current']['logo'])) {
            return $settings_data['current']['logo'];
        }
    }

    return $assets;
}

function registerShopifyWebhooks($shop, $access_token) {
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
                'X-Shopify-Access-Token: ' . $access_token
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
            return false;
        }
    }

    return true;
}