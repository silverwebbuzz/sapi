<?php // Start the session
error_reporting(E_ALL);
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

//Fetch store plan.
$sql_currentPlan = "
SELECT 
    ss.store_id,
    ss.plan_id,
    ss.order_limit AS subscription_order_limit,
    ss.features AS subscription_features,
    ss.activated_on,
    ss.cancelled_on,
    ss.status,
    ss.order_used,
    ss.email_used,
    p.name AS plan_name,
    p.price,
    p.order_limit AS plan_order_limit,
    p.email_limit,
    p.features AS plan_features,
    p.description
FROM store_subscriptions ss
JOIN plans p ON ss.plan_id = p.id
WHERE ss.store_id = ? AND ss.status = 'active'
ORDER BY ss.activated_on DESC
LIMIT 1
";
$currentPlan = DBHelper::selectOne($sql_currentPlan,"s", [$shop_id]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWB Auto PDF Invoices</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="main-header">
            <h1>SWB Auto PDF Invoices</h1>
            <div class="user-profile">
                <span><?= $shop_owner?></span>
                <div class="avatar"><?= ucfirst(substr($shop_owner,0,1))?></div>
            </div>
        </header>
<?php 
$smtp_settings_data = DBHelper::selectOne(
    "SELECT smtp_settings FROM stores WHERE `id` = ? ",
    "s", 
    [$shop_id]
);
if (!$smtp_settings_data['smtp_settings']): // You can set this based on your SMTP check logic ?>
<div id="smtp-warning" class="warning-box">
    ⚠️ Please upgrade you plan and set your SMTP settings to receive invoice emails from your defined Email Address.
</div>
<?php endif; ?>