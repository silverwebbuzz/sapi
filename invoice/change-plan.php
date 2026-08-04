<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';
require_once 'i18n.php';

// No store row yet at this point, so this first boot resolves from the
// browser's Accept-Language; it is re-run below once the store is known.
i18n_boot();

// Shop param can come from $_GET (normal nav) or from $_SESSION (if the user
// landed here via an upgrade button that lost the param). Fall back to session
// before failing so users don't see PHP warnings.
$shop = $_GET['shop'] ?? ($_SESSION['shop'] ?? '');
if (!$shop) {
    http_response_code(400);
    die(t('errors.missing_shop_param'));
}

$store_name = explode('.', $shop)[0];

$store = DBHelper::selectOne(
    "SELECT * FROM stores WHERE `shop` = ? AND `status` = ?",
    "ss",
    [$shop, "installed"]
);
if (!$store) {
    http_response_code(404);
    die(t('errors.store_not_installed'));
}
$host = $store['host'] ?? '';

// Now that we have the store, honour its saved language.
i18n_boot($store);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(i18n_locale()) ?>" dir="<?= htmlspecialchars(i18n_dir()) ?>">
<head>
  <title><?= e('billing.redirecting_title') ?></title>
  <!-- Load App Bridge v3 only. Loading the cloud auto-bootstrap alongside
       triggers a deprecated-parameters warning on init. -->
  <meta name="shopify-api-key" content="<?= htmlspecialchars(SHOPIFY_API_KEY) ?>" />
  <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
</head>
<body>
  <p><?= e('billing.redirecting_body') ?></p>
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