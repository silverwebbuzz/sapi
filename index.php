<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'invoice/shopify_functions.php';

// Handle direct access to the root URL
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    handleDirectAccess();
}

// Handle OAuth flow
if (isset($_GET['hmac']) && isset($_GET['shop']) && isset($_GET['timestamp'])) {
    //handleOAuthRequest();
    $installparams = $params = $_GET;

    if (!verifyHmac($params, SHOPIFY_API_SECRET)) die('Invalid HMAC');

    $shop = $params['shop'];
    // fetch data from store.
    $store = DBHelper::selectOne(
        "SELECT id, shop_owner, status FROM stores WHERE `shop` = ? AND `status` = ?",
        "ss", 
        [$shop, "installed"]
    );

    // If store does not exist or was uninstalled, restart installation
    if (!$store ) {
        $install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, BASE_SHOPIFY_AF_URL . 'callback');
        header("Location: $install_url");
        exit();
    }
    else 
    {
        $cookieData = [
            'shop_id' => $store['id'],
            'shop' =>  $shop,
            'shop_owner' => $store['shop_owner'],
            'host' => $_GET['host'],
            'expires' => time() + (86400 * 30) // 30 days
        ];
        $encryptedCookie =  setEncryptCookie($cookieData);
        
        // redirect to invoice homepage.
        $dashboard_redirect = "invoice/index.php";
        header("Location: $dashboard_redirect ");
    }

}
handleDirectAccess();
exit();