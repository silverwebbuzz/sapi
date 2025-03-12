<?php
require_once 'config.php';
require_once 'db.php';

// Verify HMAC
function verifyHmac($params, $secret) {
    if (empty($params['hmac'])) return false;
    $hmac = $params['hmac'];
    unset($params['hmac']);
    ksort($params);
    $query = http_build_query($params);
    return hash_equals($hmac, hash_hmac('sha256', $query, $secret));
}

$params = $_GET;
if (!verifyHmac($params, SHOPIFY_API_SECRET)) die('Invalid HMAC');

$shop = $params['shop'];
if (!preg_match('/\.myshopify\.com$/', $shop)) die('Invalid shop domain');

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
$nonce = bin2hex(random_bytes(16));
$_SESSION['nonce'] = $nonce;
$redirect_uri = urlencode(SHOPIFY_APP_URL . '/shopify/callback');
$install_url = "https://{$shop}/admin/oauth/authorize?client_id=" . SHOPIFY_API_KEY . 
               "&scope=" . SHOPIFY_APP_SCOPES . "&redirect_uri={$redirect_uri}&state={$nonce}";
header("Location: $install_url");