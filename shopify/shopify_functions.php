<?php
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

function getShopTax($shop, $access_token) {
    // GraphQL query for fetching logo (from active theme) and tax metafields
    $query = [
        'query' => '{
            shop {
                metafields(namespace: "global", first: 10) {
                    edges {
                        node {
                            key
                            value
                        }
                    }
                }
            }
        }'
    ];

    $url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/graphql.json";   
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Shopify-Access-Token: ' . $access_token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $shop_data = $data['data']['shop'] ?? [];

    // Extract tax-related metafields
    $tax_settings = [];
    $gstin = '';

    if (!empty($shop_data['metafields']['edges'])) {
        foreach ($shop_data['metafields']['edges'] as $edge) {
            $node = $edge['node'];

            // Capture GSTIN for Indian stores
            if ($node['key'] === 'gstin') {
                $gstin = $node['value'];
            }

            // Capture other tax-related metafields
            if (strpos($node['key'], 'tax_') === 0 || strpos($node['key'], 'vat_') === 0) {
                $tax_settings[$node['key']] = $node['value'];
            }
        }
    }
    return [
        'gstin' => $gstin,
        'tax_settings' => json_encode($tax_settings)
    ];
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