<?php
require_once '../config.php';
require_once '../db.php';
require_once 'shopify_functions.php';

$params = $_GET;
echo "params: " . print_r($params, true); 
if (!verifyHmac($params, SHOPIFY_API_SECRET)) die('Invalid HMAC');

$shop = $params['shop'];
if (!validateShopDomain($shop)) {
    die('Invalid shop domain format');
}
//if (!preg_match('/\.myshopify\.com$/', $shop)) die('Invalid shop domain');

// Check if shop exists
$conn = DB::getInstance();
$stmt = $conn->prepare("SELECT id FROM stores WHERE shop = ?");
$stmt->bind_param("s", $shop);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    header("Location: " . BASE_URL . "/client?shop=" . urlencode($shop) . "&hmac=" . $params['hmac']);
    exit();
}

// Start OAuth flow
$install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, SHOPIFY_APP_URL . '/shopify/callback');

header("Location: $install_url");