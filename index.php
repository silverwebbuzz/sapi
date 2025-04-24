<?php print_r($_GET);?>
<!DOCTYPE html>
<html>
<head>
  <title>Shopify App</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Shopify App Bridge -->
  <script src="https://unpkg.com/@shopify/app-bridge@3"></script>
  <script src="https://unpkg.com/@shopify/app-bridge-utils@3"></script>

  <style>
    body { font-family: sans-serif; margin: 20px; }
    .message { padding: 10px; background: #f5f5f5; border: 1px solid #ccc; }
  </style>
</head>
<body>

<div class="message">
  Welcome to your embedded Shopify app.
  <a href="home">Home test</a>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const AppBridge = window["app-bridge"];
    const createApp = AppBridge.default;
    const actions = AppBridge.actions;
    const utils = window['app-bridge-utils'];

    const host = new URLSearchParams(window.location.search).get("host");
    const shop = new URLSearchParams(window.location.search).get("shop");

    // Store in localStorage for later use
    if (shop && host) {
      localStorage.setItem("shop", shop);
      localStorage.setItem("host", host);
    }
    else
    {
        const Shop = localStorage.getItem("shop");
        alert(Shop);
    }

    const app = createApp({
      apiKey: "376ce2c1e19ffa08ea8ad26e038ff4f5", // Replace with your app's API key
      host: host,
      forceRedirect: true
    });

    // Navigation Sidebar
    const NavigationMenu = actions.NavigationMenu;
    NavigationMenu.create(app, {
      items: [
        { label: "Dashboard", destination: "/index?shop=" + shop + "&host=" + host },
        { label: "Orders", destination: "/orders?shop=" + shop + "&host=" + host },
        { label: "Settings", destination: "/settings?shop=" + shop + "&host=" + host }
      ]
    });

    // Get Session Token and use it
    utils.getSessionToken(app).then(function(token) {
      fetch("verify_token.php", {
        method: "POST",
        headers: {
          Authorization: "Bearer " + token
        }
      })
      .then(response => response.json())
      .then(data => console.log("Token Verified", data))
      .catch(error => console.error("Token verification failed", error));
    });

    window.app = app;
  });
</script>

</body>
</html>
<?php
exit;
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'invoice/shopify_functions.php';

// Handle direct access to the root URL
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    require_once 'home.php'; 
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