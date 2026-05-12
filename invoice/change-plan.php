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
  <!-- Load App Bridge v3 only. Loading the cloud auto-bootstrap alongside
       triggers a deprecated-parameters warning on init. -->
  <meta name="shopify-api-key" content="<?= htmlspecialchars(SHOPIFY_API_KEY) ?>" />
  <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
</head>
<body>
  <p>Redirecting to pricing plans...</p>
  <script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
    const AppBridge = window['app-bridge'];
    const createApp = AppBridge.createApp;
    const actions = AppBridge.actions;
    const Redirect = actions.Redirect;

    const app = createApp({
      apiKey: '<?= SHOPIFY_API_KEY ?>',
      host: '<?= $host ?>',
      forceRedirect: true,
    });

    const redirect = Redirect.create(app);
    const pricingPlansUrl = `https://admin.shopify.com/store/<?= $store_name ?>/charges/<?= SHOPIFY_APP_HANDLE ?>/pricing_plans`;

    // ✅ Redirect to pricing plans
    redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);
  });
</script>
  
</body>
</html>