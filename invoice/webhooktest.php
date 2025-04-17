<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'shopify_functions.php';
require_once '../vendor/autoload.php';

$shop = 'silverwebbuzzapp.myshopify.com';



$shop_data = DBHelper::selectOne(
    "SELECT id, shop_owner, status FROM stores WHERE `shop` = ? AND `status` = ?",
    "ss", 
    [$shop, "installed"]
);
$shop_id = $shop_data['id'];

$sql_currentPlan = "SELECT * FROM store_subscriptions ss WHERE ss.store_id = ? AND ss.status = 'active'  ORDER BY ss.activated_on DESC LIMIT 1 ";
$currentPlan = DBHelper::selectOne($sql_currentPlan, "i", [$shop_id]);

if ($currentPlan['price']!='0.00') {
    $order_number = 6308565057836;
    $generatepdf  = generatepdf($shop_id,$order_number);
    $sendemail  = sendemail($shop_id,$order_number);
}