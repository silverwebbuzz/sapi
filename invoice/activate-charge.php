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
/*if ($pending_charge['previous_plan_id'] && $pending_charge['previous_plan_id'] != 5) {
    cancelOldSubscription($shop, $access_token);
}*/

$Plan = DBHelper::selectOne("SELECT * FROM `plans` where id = ?","i",[$pending_charge['plan_id']]);


$start_date = date("Y-m-d H:i:s");
// For demonstration, we set the end_date to one year from now.
$end_date = date("Y-m-d H:i:s", strtotime("+1 month"));

$insertSql = "INSERT INTO store_subscriptions 
(store_id, plan_id, charge_id, order_limit, email_limit, order_used, email_used, features, activated_on, billing_on, cancelled_on, status)
VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, 'active')";

$subscription_id = DBHelper::insert($insertSql,"iiiiissss",
[$shop_id,
$pending_charge['plan_id'],
$charge_id,
$Plan['order_limit'],
$Plan['email_limit'],
$Plan['features'],
$start_date,
$activated_charge['billing_on'],
$end_date]
);

echo "<pre>";
print_r($activated_charge); exit;
// After handling the charge activation
$redirect_url = SHOPIFY_APP_URL . "?shop=" . urlencode($shop);
?>
<!DOCTYPE html>
<html>
<head>
    <script type="text/javascript">
        // Return to app interface in parent window
        if (window.self !== window.top) {
            window.top.location.href = "<?= $redirect_url ?>";
        } else {
            window.location.href = "<?= $redirect_url ?>";
        }
    </script>
</head>
<body>
    <!-- Loading spinner or message -->
    <div class="loading">Completing setup...</div>
</body>
</html>
<?php
exit();
//header("Location: /billing?subscription=success");
//exit;