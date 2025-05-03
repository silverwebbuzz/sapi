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
        try {
            // Debug: Verify variables
            console.log('API Key:', '<?= SHOPIFY_API_KEY?>');
            console.log('Host:', '<?= $host?>');
            console.log('Store Name:', '<?= $store_name?>');
            console.log('App Handle:', '<?= SHOPIFY_APP_HANDLE?>');

            const { createApp, actions } = window.shopify.app;
            const { Redirect } = actions;

            const app = createApp({
                apiKey: '<?= SHOPIFY_API_KEY?>',
                host: '<?= $host?>',
                forceRedirect: true,
            });

            const pricingPlansUrl = `https://admin.shopify.com/store/<?= urlencode($store_name) ?>/charges/<?= urlencode(SHOPIFY_APP_HANDLE) ?>/pricing_plans`;
            console.log('Redirecting to:', pricingPlansUrl);

            const redirect = Redirect.create(app);
            redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);
        } catch (error) {
            console.error('Error:', error);
        }
    });

</script>
</head>
<body>
  <p>Redirecting to pricing plans...</p>

  
</body>
</html>