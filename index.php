<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'invoice/shopify_functions.php';

// Handle direct access to the root URL
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Install App</title>
    </head>
    <body>
        <h1>Please install this app via Shopify Admin</h1>
        <p>If you're a store owner, please <a href="https://www.shopify.com/login" target="_blank">login to Shopify</a> 
        and install this app from your admin dashboard.</p>
        <p>If you're being redirected from Shopify Admin, <a href="<?= LIVE_SHOPIFY_APP_URL?>">click here</a> to continue installation.</p>
    </body>
    </html>
    <?php 
}

if(isset($_GET['charge_id'])){
    $shop_data = DBHelper::selectOne("SELECT * FROM stores WHERE `id` = ? ","s", [$shop_id]);
    $access_token = $shop_data['access_token'];
    getChargeDetails($shop, $access_token, $_GET['charge_id']);
}

// Handle OAuth flow
if (isset($_GET['hmac']) && isset($_GET['shop']) && isset($_GET['timestamp'])) {
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
exit();