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
$stmt = $conn->prepare("SELECT id,status FROM stores WHERE shop = ?");
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

$stmt = $conn->prepare("
    SELECT p.name AS plan_name, p.price, p.order_limit, s.end_date, st.id as store_id
    FROM store_subscriptions s
    JOIN stores st ON s.store_id = st.id
    JOIN plans p ON s.plan_id = p.id
    WHERE st.shop = ? AND s.status = 'active'
");
$stmt->bind_param("s", $shop);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Store is subscribed, show plan details
    $subscription = $result->fetch_assoc();
    $dashboard_redirect = "invoice/index.php?shop_id=".$subscription['store_id'];
    header("Location: $dashboard_redirect ");
} else {
    // Store is NOT subscribed, show pricing plans
    include 'pricing.php';
}

exit();