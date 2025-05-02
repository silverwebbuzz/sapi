<?php
 error_reporting(E_ALL);
 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';

$shop = $_GET['shop'];
$store_name = explode('.', $shop)[0];

$store = DBHelper::selectOne(
  "SELECT * FROM stores WHERE `shop` = ? AND `status` = ?",
  "ss", 
  [$shop, "installed"]
);
$host = $store['host'];

$api_key = 'YOUR_SHOPIFY_API_KEY'; // From your app setup
?>
<!DOCTYPE html>
<html>
<head>
  <title>Redirecting...</title>
  <!-- Shopify App Bridge must be first -->
  <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
  <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
        // Use the new Shopify namespace
        const { createApp, actions } = window.shopify.app;
        const { Redirect } = actions;

        const app = createApp({
            apiKey: '<?= SHOPIFY_API_KEY?>',
            host: '<?= $host?>',
            forceRedirect: true,
        });

        const redirect = Redirect.create(app);
        const pricingPlansUrl = `https://admin.shopify.com/store/<?= urlencode($store_name) ?>/charges/<?= urlencode(SHOPIFY_APP_HANDLE) ?>/pricing_plans`;

        // Perform the redirect
        redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);
    });
</script>
</head>
<body>
  <p>Redirecting to pricing plans...</p>

  
</body>
</html>