<?php // At the top of your PHP page (before any output)
header("Content-Security-Policy: frame-ancestors https://*.myshopify.com https://admin.shopify.com;");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';
$shop = $_GET['shop'];
$store = DBHelper::selectOne(
  "SELECT * FROM stores WHERE `shop` = ? AND `status` = ?",
  "ss", 
  [$shop, "installed"]
);
$shop_id = $store['id'];
$shop_owner = $store['shop_owner'];
$host = $store['host'];

//Fetch store plan.
$sql_currentPlan = "
SELECT *
FROM store_subscriptions ss
WHERE ss.store_id = ? AND ss.status = 'active'
ORDER BY ss.activated_on DESC
LIMIT 1
";

$currentPlan = DBHelper::selectOne($sql_currentPlan, "i", [$shop_id]);
if (!isset($currentPlan) || empty($currentPlan)) {
  header("Location: change-plan.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWB Auto PDF Invoices</title>
    <!-- Shopify App Bridge must be first -->
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge-utils.js"></script>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        // Access App Bridge through the Shopify namespace
       // const { createApp, actions } = window.shopify.app;
        const { createApp, actions } = window.shopifyAppBridge;
        const { getSessionToken } = window.shopify.appBridgeUtils;
        const { Redirect, NavigationMenu } = actions;

        const app = createApp({
          apiKey: '<?= SHOPIFY_API_KEY?>',
          host: '<?= $host?>',
          forceRedirect: true,
        });

        // Get and send session token
        getSessionToken(app).then((token) => {
            fetch('../verify_token.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            }).then(res => res.json()).then(data => {
                console.log(data.message || 'Verified!');
                const redirect = Redirect.create(app);
                const pricingPlansUrl = `https://admin.shopify.com/store/<?= $store_name ?>/charges/<?= SHOPIFY_APP_HANDLE ?>/pricing_plans`;
                //redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);
            }).catch(err => {
                console.log('Auth failed: ' + err.message);
            });
        });

        // Create navigation menu
        const navigationMenu = NavigationMenu.create(app, {
            items: [
                {
                    label: 'Dashboard',
                    destination: '/swb-auto-pdf-invoices?shop=<?= $shop ?>&host=<?= $host ?>',
                },
                {
                    label: 'Orders List',
                    destination: '/order?shop=<?= $shop ?>&host=<?= $host ?>',
                },
                {
                    label: 'Settings',
                    destination: '/settings?shop=<?= $shop ?>&host=<?= $host ?>',
                },
            ],
        });

        window.app = app; // Set globally if needed
    });
    </script>
    <!-- Other scripts and styles -->
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