<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';

$cookieData = decryptCookie($_COOKIE['swb_auth']);
$shop_id = $cookieData['shop_id'];
$shop = $cookieData['shop'];
$shop_owner = $cookieData['shop_owner'];

$shop_data = DBHelper::selectOne("SELECT * FROM stores WHERE `id` = ? ","s", [$shop_id]);
$access_token = $shop_data['access_token'];

$current_charge_id = DBHelper::selectOne("SELECT charge_id FROM store_subscriptions WHERE `store_id` = ? ORDER BY activated_on DESC ","s", [$shop_id]);
$charge_id = $current_charge_id['charge_id'];

if($charge_id!='') {
    cancelOldSubscription($shop, $access_token, $charge_id);

    //Verify cancellation
    $affectedRows = DBHelper::execute(
        "UPDATE `store_subscriptions` SET  `status` = 'cancelled', `cancelled_on` = NOW()  WHERE plan_id = ? and store_id = ? ",
        "ss",
        [$_GET['plan_id'],$shop_id]
    );

    $affectedRows = DBHelper::execute(
        "UPDATE `store_subscriptions` SET  `status` = 'active'  WHERE plan_id = 5 and store_id = ? ",
        "ss",
        [$_GET['plan_id'],$shop_id]
    );
}
// Redirect to Shopify admin
$billing_url = "billing.php";
header("Location: $billing_url");
exit;