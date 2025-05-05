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
  <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
    try {
      const apiKey = '<?= SHOPIFY_API_KEY ?>';
      const host = '<?= $host ?>';
      const storeName = '<?= $store_name ?>';
      const appHandle = '<?= SHOPIFY_APP_HANDLE ?>';

      console.log('API Key:', apiKey);
      console.log('Host:', host);
      console.log('Store Name:', storeName);
      console.log('App Handle:', appHandle);

      const AppBridge = window['app-bridge'];
      const actions = AppBridge.actions;
      const Redirect = actions.Redirect;

      const app = AppBridge.createApp({
        apiKey: apiKey,
        host: host,
        forceRedirect: true,
      });

      const redirect = Redirect.create(app);
      const pricingPlansUrl = `https://admin.shopify.com/store/${encodeURIComponent(storeName)}/charges/${encodeURIComponent(appHandle)}/pricing_plans`;

      console.log('Redirecting to:', pricingPlansUrl);

      redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);
    } catch (error) {
      console.error('App Bridge Error:', error);
    }
  });
</script>
</head>
<body>
  <p>Redirecting to pricing plans...</p>

  
</body>
</html>