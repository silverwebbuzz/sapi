<?php // At the top of your PHP page (before any output)
header("Content-Security-Policy: frame-ancestors https://*.myshopify.com https://admin.shopify.com;");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';
require_once 'i18n.php';
$shop = $_GET['shop'];
$store = DBHelper::selectOne(
  "SELECT * FROM stores WHERE `shop` = ? AND `status` = ?",
  "ss",
  [$shop, "installed"]
);
$shop_id = $store['id'];

// Resolve the language for this request before anything renders:
// merchant's saved choice -> Shopify shop locale -> browser -> English.
i18n_boot($store);
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
  header("Location: change-plan.php?shop=".$shop);
  exit();
}
// Reset usage counters if the billing period has rolled over (monthly quota).
$currentPlan = applyMonthlyUsageReset($currentPlan);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_locale()) ?>" dir="<?= htmlspecialchars(i18n_dir()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e('app.name') ?></title>
    <meta name="shopify-api-key" content="<?= htmlspecialchars(SHOPIFY_API_KEY) ?>" />
    <!-- App Bridge v3 (manual init). Do NOT also load the auto-bootstrap
         shopifycloud/app-bridge.js — having both causes the v3 lib to log
         "using deprecated parameters" warnings on every page load. -->
    <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils@3"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const AppBridge = window['app-bridge'];
        const createApp = AppBridge.createApp;
        const { Redirect, NavigationMenu } = AppBridge.actions;
        const utils = window['app-bridge-utils'];

        if (utils) {
            const app = createApp({
                apiKey: '<?= SHOPIFY_API_KEY ?>',
                host: '<?= $host ?>',
                forceRedirect: true,
            });

            // Function to create the navigation menu after app is created
            const createNavigationMenu = () => {
                const navigationMenu = NavigationMenu.create(app, {
                    items: [
                        { label: <?= json_encode(t('nav.dashboard')) ?>, destination: '/index?shop=<?= $shop ?>&host=<?= $host ?>' },
                        { label: <?= json_encode(t('nav.orders_menu')) ?>, destination: '/order?shop=<?= $shop ?>&host=<?= $host ?>' },
                        { label: <?= json_encode(t('nav.bulk_download')) ?>, destination: '/bulk-download?shop=<?= $shop ?>&host=<?= $host ?>' },
                        { label: <?= json_encode(t('nav.packing_slips')) ?>, destination: '/packing-slip?shop=<?= $shop ?>&host=<?= $host ?>' },
                        { label: <?= json_encode(t('nav.settings')) ?>, destination: '/settings?shop=<?= $shop ?>&host=<?= $host ?>' },
                        { label: <?= json_encode(t('nav.help')) ?>, destination: '/help?shop=<?= $shop ?>&host=<?= $host ?>' }
                    ]
                });
                console.log("Navigation Menu Created:", navigationMenu);
            };

            utils.getSessionToken(app).then((token) => {
                fetch('../verify_token.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token
                    }
                }).then(res => res.json()).then(data => {
                    console.log(data.message || 'Verified!');
                    // Redirect logic if needed
                    createNavigationMenu();
                }).catch(err => {
                    console.error('Auth failed:', err.message);
                });
            }).catch(error => {
                console.error('Failed to get session token:', error);
            });
        } else {
            console.error('App Bridge Utils not loaded correctly.');
        }
    });
    </script>
    <!-- Other scripts and styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: time() ?>">
    <?php if (i18n_is_rtl()): ?>
    <!-- Loaded only for Arabic/Hebrew so LTR merchants pay nothing for it. -->
    <link rel="stylesheet" href="../css/rtl.css?v=<?= @filemtime(__DIR__ . '/../css/rtl.css') ?: time() ?>">
    <?php endif; ?>
</head>
<body>
    <div class="dashboard-container">
        <header class="main-header">
            <h1><?= e('app.name') ?></h1>
            <div class="user-profile">
                <span><?= $shop_owner?></span>
                <div class="avatar"><?= ucfirst(substr($shop_owner,0,1))?></div>
            </div>
        </header>