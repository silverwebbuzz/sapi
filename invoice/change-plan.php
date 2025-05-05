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
  <!-- Load App Bridge -->
  <meta name="shopify-api-key" content="<?= htmlspecialchars(SHOPIFY_API_KEY) ?>" />
  <script src="https://unpkg.com/@shopify/app-bridge@2.0.0/umd/index.js"></script>
</head>
<body>
  <p>Redirecting to pricing plans...</p>
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    function initAppBridge() {
      if (!window.appBridge || !window.appBridge.default) {
        return setTimeout(initAppBridge, 100);
      }

      const apiKey = '<?= SHOPIFY_API_KEY ?>';
      const host = '<?= $host ?>';
      const storeName = '<?= $store_name ?>';
      const appHandle = '<?= SHOPIFY_APP_HANDLE ?>';

      const createApp = window.appBridge.default;
      const { Redirect } = window.appBridge.actions;

      const app = createApp({
        apiKey,
        host,
        forceRedirect: true,
      });

      const redirect = Redirect.create(app);
      const pricingPlansUrl = `https://admin.shopify.com/store/${encodeURIComponent(storeName)}/charges/${encodeURIComponent(appHandle)}/pricing_plans`;

      redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);
    }

    initAppBridge();
  });
</script>
  
</body>
</html>