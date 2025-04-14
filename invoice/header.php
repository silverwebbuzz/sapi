<?php // Start the session
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';

// Validate request origin
$allowed_origins = [
    'https://admin.shopify.com',
    'https://*.shopify.com'
];
print_r($_GET);
if (!in_array($_SERVER['HTTP_REFERER'], $allowed_origins)) {
    //ttp_response_code(403);
    //die(json_encode(['error' => 'Invalid origin']));
}

$cookieData = decryptCookie($_COOKIE['swb_auth']);
$shop_id = $cookieData['shop_id'];
$shop = $cookieData['shop'];
$shop_owner = $cookieData['shop_owner'];

//Fetch store plan.
$sql_currentPlan = "
SELECT 
    ss.store_id,
    ss.plan_id,
    ss.charge_id,
    ss.order_limit AS subscription_order_limit,
    ss.features AS subscription_features,
    ss.activated_on,
    ss.cancelled_on,
    ss.next_charge_date,
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