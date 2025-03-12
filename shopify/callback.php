<?php // At the VERY TOP of callback.php
ob_start(); // Start output buffering

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
    // Save store details
    $conn = DB::getInstance();
    $stmt = $conn->prepare("INSERT INTO stores (shop, access_token, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
    $stmt->bind_param("ss", $shop, $response['access_token']);
    $stmt->execute();

// After processing, clean buffers before sending JSON
ob_end_clean();
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
        ]),
        CURLOPT_RETURNTRANSFER => true
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

 exit;
    $redirect_url = "https://{$shop}/admin/apps/" . SHOPIFY_API_KEY;
    header("Location: " . $redirect_url);
    exit;
} else {
    die('Installation failed');
}
?>