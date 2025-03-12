<?php
require_once 'config.php';
require_once 'db.php';
require_once 'shopify/shopify_functions.php';

// Verify HMAC
$installparams = $params = $_GET;

if (!verifyHmac($params, SHOPIFY_API_SECRET)) die('Invalid HMAC');

$shop = $params['shop'];

// Check if installed
$conn = DB::getInstance();
$stmt = $conn->prepare("SELECT id FROM stores WHERE shop = ?");
$stmt->bind_param("s", $shop);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {

    $install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, SHOPIFY_APP_URL . '/shopify/callback');
    header("Location: $install_url");
    exit();
}

// Set security headers for embedded app
header("Content-Security-Policy: frame-ancestors https://*.shopify.com https://admin.shopify.com");
echo "<h1>Welcome to SWB Auto PDF Invoices</h1>";