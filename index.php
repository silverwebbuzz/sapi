<?php
require_once 'config.php';
require_once 'db.php';
require_once 'shopify/shopify_functions.php';

// Handle direct access to the root URL
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    handleDirectAccess();
}

// Handle OAuth flow
if (isset($_GET['hmac']) && isset($_GET['shop']) && isset($_GET['timestamp'])) {
    handleOAuthRequest();
}

if(isset($_SESSION['shop']) && $_SESSION['shop'] === $shop && isset($_SESSION['access_token'])){
    $dashboard_redirect = "invoice/index.php";
    header("Location: $dashboard_redirect ");
    exit();
}
handleDirectAccess();
exit();