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

    $store = DBHelper::selectOne(
        "SELECT id, status FROM stores WHERE `shop` = ? AND `status` = ?",
        "ss", 
        [$shop, "installed"]
    );

    // If store does not exist or was uninstalled, restart installation
    if (!$store ) {
        $install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, SHOPIFY_APP_URL . '/shopify/callback');
        header("Location: $install_url");
        exit();
    }


    $subscription = DBHelper::selectOne("SELECT p.name AS plan_name, p.price, p.order_limit, s.cancelled_on, st.id as store_id
        FROM store_subscriptions s
        JOIN stores st ON s.store_id = st.id
        JOIN plans p ON s.plan_id = p.id
        WHERE st.shop = ? AND s.status = 'active'",
        "s", 
        [$shop]
    );

    if ($subscription) {
        
        // After getting $accessToken successfully:
        $cookieData = [
            'shop_id' => $subscription['store_id'],
            'expires' => time() + (86400 * 30) // 30 days
        ];
        $encryptedCookie = encryptCookie($cookieData);
        setcookie(
            'swb_auth',
            $encryptedCookie,
            [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'domain' => '.silverwebbuzz.com', // Allow subdomains
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None' // Required for Shopify iframe
            ]
        );

        $dashboard_redirect = "invoice/index.php?shop_id=".$subscription['store_id'];
        header("Location: $dashboard_redirect ");
    }

}
handleDirectAccess();
exit();