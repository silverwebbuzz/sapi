<?php
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
echo $shop_id = $shop_data['id'];

$sql_currentPlan = "SELECT * FROM store_subscriptions ss WHERE ss.store_id = ? AND ss.status = 'active'  ORDER BY ss.activated_on DESC LIMIT 1 ";
$currentPlan = DBHelper::selectOne($sql_currentPlan, "i", [$shop_id]);
print_r($currentPlan['price']);
if ($currentPlan['price']!='0.00') {
    echo $order_id = 31;
    $generatepdf  = generatepdf($shop_id,$order_id);
    $sendemail  = sendemail($shop_id,$order_id);
}