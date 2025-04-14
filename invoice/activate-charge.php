<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';

$cookieData = decryptCookie($_COOKIE['swb_auth']);
$shop_id = $cookieData['shop_id'];
$shop = $cookieData['shop'];
$shop_owner = $cookieData['shop_owner'];

$shop_data = DBHelper::selectOne("SELECT * FROM stores WHERE `id` = ? ","s", [$shop_id]);
$access_token = $shop_data['access_token'];

$charge_id = $_GET['charge_id'];

$activated_charge = activateRecurringCharge($shop, $access_token, $charge_id);
if (!$activated_charge) {
    throw new Exception("Failed to activate charge");
}

$pending_charge = DBHelper::selectOne("SELECT * FROM pending_charges WHERE charge_id = ?","i",[$charge_id]);
if ($pending_charge['previous_plan_id']) {

    $current_charge_id = DBHelper::selectOne("SELECT charge_id FROM store_subscriptions WHERE `store_id` = ? ORDER BY activated_on DESC ","s", [$shop_id]);
    $charge_id = $current_charge_id['charge_id'];
    
    if($charge_id!='')
    cancelOldSubscription($shop, $access_token, $charge_id);
    //Verify cancellation
    $affectedRows = DBHelper::execute(
        "UPDATE `store_subscriptions` SET  `status` = 'cancelled', `cancelled_on` = NOW()  WHERE plan_id = ? and store_id = ? ",
        "ss",
        [$pending_charge['plan_id'],$shop_id]
    );
    
}

$Plan = DBHelper::selectOne("SELECT * FROM `plans` where id = ?","i",[$pending_charge['plan_id']]);

$start_date = date("Y-m-d H:i:s");
// For demonstration, we set the end_date to one year from now.
$end_date = date("Y-m-d H:i:s", strtotime("+1 month"));

$insertSql = "INSERT INTO store_subscriptions 
(store_id, plan_id, charge_id, order_limit, email_limit, order_used, email_used, features, activated_on, billing_on)
VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?)";

$subscription_id = DBHelper::insert($insertSql,"iiiiisss",
[$shop_id,
$pending_charge['plan_id'],
$charge_id,
$Plan['order_limit'],
$Plan['email_limit'],
$Plan['features'],
$start_date,
$activated_charge['billing_on']]
);

 // Clear pending charge table.
 $affectedRows = DBHelper::execute(
    "DELETE FROM pending_charges WHERE charge_id = ?",
    "i",
    [$charge_id]
);

// Extract store name from domain
$store_name = explode('.', $shop)[0];

// Redirect to Shopify admin
$admin_url = "https://admin.shopify.com/store/".$store_name."/apps/".SHOPIFY_APP_HANDLE;
header("Location: $admin_url");
exit;