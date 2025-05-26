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

function getAllFiles($shop, $access_token) {

    $graphqlEndpoint = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/graphql.json";
    $query = <<<GQL
    {
      shop {
        brand {
          logo {
            image {
              url
              altText
            }
          }
        }
      }
    }
    GQL;

    $payload = json_encode(['query' => $query]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $graphqlEndpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Shopify-Access-Token: $access_token"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // Debug: Uncomment if you want to inspect the response
    // echo "<pre>"; print_r($result); echo "</pre>";

    if (isset($result['data']['shop']['brand']['logo']['image']['url'])) {
        return $result['data']['shop']['brand']['logo']['image']['url'];
    }

    return null;

}


function createRecurringCharge($shop,$access_token, $charge_data){
    $Url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/recurring_application_charges.json";

    $ch = curl_init($Url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: {$access_token}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($charge_data)
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 201) {
        error_log("Failed to create charge: " . $response);
        return false;
    }
    
    $response_data = json_decode($response, true);
    return $response_data['recurring_application_charge'];
}

function activateRecurringCharge($shop, $access_token, $charge_id) {
    $url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/recurring_application_charges/{$charge_id}/activate.json";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: {$access_token}",
            "Content-Type: application/json"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Failed to activate charge: " . $response);
        return false;
    }
    
    $response_data = json_decode($response, true);
    return $response_data['recurring_application_charge'];
}

function cancelOldSubscription($shop, $access_token,$charge_id) {
    
    $api_url = "https://{$shop}/admin/api/2024-01/recurring_application_charges/$charge_id.json";

    //Initialize cURL request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: $access_token",
            "Content-Type: application/json"
        ]
    ]);

    // Execute the API call
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http_code;
}

function getChargeDetails($shop, $access_token, $charge_id) {
    $url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/recurring_application_charges/{$charge_id}.json";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: {$access_token}"
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Failed to get charge details: " . $response);
        return false;
    }
    
    return $response_data = json_decode($response, true);
    //return $response_data['recurring_application_charge'];
}


function registerShopifyWebhooks($shop, $access_token) {
    $webhook_url = BASE_SHOPIFY_AF_URL . 'webhook'; 
    $topics = [
        'orders/create',
        'orders/paid',
        'app/uninstalled',
        'app_subscriptions/update'
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
            //return "Webhook for {$topic} registered successfully!";
            //return true;
        } else {
            //return "Failed to register webhook for {$topic}. Response: " . $result;
            //return false;
        }
    }

    return true;
}

function listWebhooks($shop, $accessToken) {
    $url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/webhooks.json";
    
    $headers = [
        "Content-Type: application/json",
        "X-Shopify-Access-Token: $accessToken"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['webhooks'] ?? [];
}

// Helper function to extract ID from GraphQL ID string
function extractIdFromGql($gqlId) {
    $parts = explode('/', $gqlId);
    return end($parts);
}

function fetchSubscriptionWithGraphQL($shop, $accessToken, $chargeId) {
    $graphqlEndpoint = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/graphql.json";
    $query = '{
        appInstallation {
          activeSubscriptions {
            id
            name
            status
            createdAt
            currentPeriodEnd
            test
            lineItems {
              id
              plan {
                pricingDetails {
                  ... on AppRecurringPricing {
                    interval
                    price {
                      amount
                      currencyCode
                    }
                  }
                  ... on AppUsagePricing {
                    terms
                    cappedAmount {
                      amount
                      currencyCode
                    }
                  }
                }
              }
            }
          }
        }
    }';
    
    $variables = [
        'id' => "gid://shopify/AppSubscription/$chargeId"
    ];
    
    $payload = json_encode([
        'query' => $query,
        'variables' => $variables
    ]);
    
    $headers = [
        'Content-Type: application/json',
        'X-Shopify-Access-Token: ' . $accessToken
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $graphqlEndpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("cURL Error: " . $error);
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['errors'])) {
        error_log("GraphQL Error: " . print_r($data['errors'], true));
        return null;
    }
    
    if (!isset($data['data']['appInstallation'])) {
        error_log("Invalid GraphQL response structure");
        return null;
    }
    
    return normalizeGraphQLData($data['data']['appInstallation']);
}

function normalizeGraphQLData($subscription) {
    // Handle both direct subscription data and appInstallation->activeSubscriptions array
    $subscriptionData = $subscription;
    if (isset($subscription['activeSubscriptions'][0])) {
        $subscriptionData = $subscription['activeSubscriptions'][0];
    }
    
    $lineItem = $subscriptionData['lineItems'][0] ?? [];
    $pricingDetails = $lineItem['plan']['pricingDetails'] ?? [];
    
    return [
        'id' => extractIdFromGql($subscriptionData['id']),
        'name' => $subscriptionData['name'],
        'status' => strtolower($subscriptionData['status']),
        'created_at' => $subscriptionData['createdAt'],
        'updated_at' => $subscriptionData['createdAt'], // Using createdAt since updatedAt doesn't exist
        'activated_on' => $subscriptionData['createdAt'], // Using createdAt as activation date
        'cancelled_on' => null, // Not available in response
        'trial_ends_on' => null, // Not available in response
        'current_period_end' => $subscriptionData['currentPeriodEnd'],
        'billing_interval' => strtolower($pricingDetails['interval'] ?? 'month'), // Convert ANNUAL to annual
        'interval_count' => 1, // Default to 1 since not available in response
        'price' => $pricingDetails['price']['amount'] ?? null,
        'currency' => $pricingDetails['price']['currencyCode'] ?? 'USD',
        'capped_amount' => null, // Not available in this response
        'terms' => null, // Not available in this response
        'is_test' => $subscriptionData['test'] ?? false,
        'next_billing_date' => $subscriptionData['currentPeriodEnd']
    ];
}

function calculatePlanLimits($planName, $price, $billingInterval) {
    $planName = strtolower($planName);
    $isAnnual = strtolower($billingInterval) === 'annual';
    $unlimited = PHP_INT_MAX;
    
    switch ($planName) {
        case 'starter':
            if ($price == 9.99) {
                return [
                    'order_limit' => 200,
                    'email_limit' => 300
                ];
            } elseif ($price == 109.99) {
                return [
                    'order_limit' => $isAnnual ? (200 * 12) : 200,
                    'email_limit' => $isAnnual ? (300 * 12) : 300
                ];
            }
            break;
            
        case 'growth':
            if ($price == 29.99) {
                return [
                    'order_limit' => 1200,
                    'email_limit' => 1500
                ];
            } elseif ($price == 319.99) {
                return [
                    'order_limit' => $isAnnual ? (1200 * 12) : 1200,
                    'email_limit' => $isAnnual ? (1500 * 12) : 1500
                ];
            }
            break;
            
        case 'premium':
            if ($price == 49.99) {
                return [
                    'order_limit' => 5000,
                    'email_limit' => 6000
                ];
            } elseif ($price == 529.99) {
                return [
                    'order_limit' => $isAnnual ? (5000 * 12) : 5000,
                    'email_limit' => $isAnnual ? (6000 * 12) : 6000
                ];
            }
            break;
    }
    
    return [
        'order_limit' => 20,
        'email_limit' => 20
    ];
}