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
$stmt = $conn->prepare("SELECT status FROM stores WHERE shop = ?");
$stmt->bind_param("s", $shop);
$stmt->execute();
$result = $stmt->get_result();
$store = $result->fetch_assoc();

// If store does not exist or was uninstalled, restart installation
if (!$store || $store['status'] === 'uninstalled') {
    $install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, SHOPIFY_APP_URL . '/shopify/callback');
    header("Location: $install_url");
    exit();
}

header("Location: dashboard?shop=$shop");
exit();

// Set security headers for embedded app
header("Content-Security-Policy: frame-ancestors https://*.shopify.com https://admin.shopify.com");
echo "<h1>Welcome to SWB Auto PDF Invoices</h1>";