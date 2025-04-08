<?php // Start the session
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config.php'; 
require_once '../db.php';
require_once '../shopify/shopify_functions.php';
require_once 'helper.php';

// Database connection
$conn = DB::getInstance();

$cookieData = decryptCookie($_COOKIE['swb_auth']);
$shop_id = $cookieData['shop_id'];

    // Fetch Store details.
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param("s", $shop_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $store = $result->fetch_assoc();
    //$store['shop'] = 'silverwebbuzzapp.myshopify.com';

    //Fetch store plan.
    $sql = "
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
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $shop_id);
    $stmt->execute();
    $curr_result = $stmt->get_result();
    $currentPlan = $curr_result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWB Auto PDF Invoices</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="main-header">
            <h1>SWB Auto PDF Invoices</h1>
            <div class="user-profile">
                <span><?= $store['shop_owner']?></span>
                <div class="avatar"><?= ucfirst(substr($store['shop_owner'],0,1))?></div>
            </div>
        </header>