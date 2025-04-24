<?php // At the top of your PHP page (before any output)
header("Content-Security-Policy: frame-ancestors https://*.myshopify.com https://admin.shopify.com;");

// Start the session
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';

$cookieData = decryptCookie($_COOKIE['swb_auth']);
$shop_id = $cookieData['shop_id'];
$shop = $cookieData['shop'];
$host = $cookieData['host'];
$shop_owner = $cookieData['shop_owner'];

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
    <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils@3"></script>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        const AppBridge = window['app-bridge'];
        const createApp = AppBridge.default;
        const actions = AppBridge.actions;
        const Redirect = actions.Redirect;
        const utils = window['app-bridge-utils'];
        // ✅ Get NavigationMenu from actions
        const NavigationMenu = actions.NavigationMenu;

        const app = createApp({
          apiKey: '<?= SHOPIFY_API_KEY?>',
          host: '<?= $host?>',
          forceRedirect: true,
        });

        // Get and send session token
        utils.getSessionToken(app).then((token) => {
            fetch('../verify_token.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            }).then(res => res.json()).then(data => {
                console.log(data.message || 'Verified!');
                alert(data.shop);
                const redirect = Redirect.create(app);
                const pricingPlansUrl = `https://admin.shopify.com/store/<?= $store_name ?>/charges/<?= SHOPIFY_APP_HANDLE ?>/pricing_plans`;
                //redirect.dispatch(Redirect.Action.REMOTE, pricingPlansUrl);

            }).catch(err => {
                console.log('Auth failed: ' + err.message);
            });
        });

        // ✅ Now use NavigationMenu
        const navigationMenu = NavigationMenu.create(app, {
            items: [
                {
                    label: 'Dashboard',
                    destination: '/index?shop=<?php echo $shop; ?>&host=<?php echo $host; ?>',
                },
                {
                    label: 'Orders List',
                    destination: '/order?shop=<?php echo $shop; ?>&host=<?php echo $host; ?>',
                },
                {
                    label: 'Settings',
                    destination: '/settings?shop=<?php echo $shop; ?>&host=<?php echo $host; ?>',
                },
            ],
        });
        console.log(actions);
        // Set this globally if needed
        window.app = app;
    });
  </script>
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