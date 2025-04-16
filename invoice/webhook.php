<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'shopify_functions.php';
require_once '../vendor/autoload.php';
 
// Verify webhook HMAC
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
$data = file_get_contents('php://input');
$calculated_hmac = base64_encode(hash_hmac('sha256', $data, SHOPIFY_API_SECRET, true));

if (!hash_equals($hmac, $calculated_hmac)) {
    http_response_code(401);
    die('Invalid webhook HMAC');
}

// Decode webhook data
$webhook = $order = json_decode($data, true);
$shop = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
$topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC']; // Get webhook topic
$cdate = date("Y-m-d H:i:s");

// webhook logs.
$insertSql = "INSERT INTO webhook (shop, topic, orders, cdate) VALUES (?, ?, ?, ?)";
$webhook_id = DBHelper::insert($insertSql,"ssss",[$shop, $topic, $data, $cdate]);
// webhook logs end.
exit();