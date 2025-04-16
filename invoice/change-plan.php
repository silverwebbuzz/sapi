<?php
 error_reporting(E_ALL);
 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 
 require_once '../config/config.php';
 require_once '../config/db.php';

$cookieData = decryptCookie($_COOKIE['swb_auth']);
$shop_id = $cookieData['shop_id'];
$shop = $cookieData['shop'];
$host = $cookieData['host'];
$shop_owner = $cookieData['shop_owner'];

// change-plan.php
$store_name = explode('.', $shop)[0];

$api_key = 'YOUR_SHOPIFY_API_KEY'; // From your app setup
?>
<!DOCTYPE html>
<html>
<head>
  <title>Redirecting...</title>
  <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
</head>
<body>
  <p>Redirecting to pricing plans...</p>

  <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () {
      const AppBridge = window['app-bridge'];
      const createApp = AppBridge.default;
      const actions = AppBridge.actions;
      const Redirect = actions.Redirect;

      const app = createApp({
        apiKey: '<?= SHOPIFY_API_KEY?>',
        host: '<?= $host?>',
        forceRedirect: true,
      });

      const redirect = Redirect.create(app);

      const pricingPlansUrl = `https://admin.shopify.com/store/<?= $store_name?>/charges/<?= SHOPIFY_APP_HANDLE?>/pricing_plans`;

      // Redirect outside iframe
      redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);
    });
  </script>
</body>
</html>