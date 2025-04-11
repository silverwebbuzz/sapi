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

$current_sub = DBHelper::selectOne("SELECT plan_id FROM store_subscriptions WHERE `store_id` = ? ","s", [$shop_id]);

$plan_id = (int)$_GET['plan_id'];
$plan = DBHelper::selectOne("SELECT * FROM `plans` where id = ?","i",[$plan_id]);

$charge_data = [
    'recurring_application_charge' => [
        'name' => $plan['name'] . ' Plan',
        'price' => $plan['price'],
        'return_url' => BASE_SHOPIFY_AF_URL."activate-charge?shop_id=$shop_id",
        'test' => true, // Set to true for development
        'terms' => 'Monthly subscription',
        'trial_days' => 0
    ]
];

$charge = createRecurringCharge($shop, $access_token, $charge_data);
if (!$charge) {
    throw new Exception("Failed to create subscription charge");
}

$pending_charge_sql = "INSERT INTO pending_charges 
(store_id, plan_id, charge_id, amount, previous_plan_id, confirmation_url)
VALUES (?, ?, ?, ?, ?, ?)";
DBHelper::insert($pending_charge_sql,"ssssss",
[$shop_id,
$plan_id,
$charge['id'],
$charge_data['recurring_application_charge']['price'],
$charge['confirmation_url']]
);

// Redirect to Shopify approval screen
header("Location: " . $charge['confirmation_url']);
exit;