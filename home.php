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
  Welcome to your embedded Shopify app. HOME page
  <a href="index">Index test</a>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const AppBridge = window["app-bridge"];
    const createApp = AppBridge.default;
    const actions = AppBridge.actions;
    const utils = window['app-bridge-utils'];

    const host = new URLSearchParams(window.location.search).get("host");
    const shop = new URLSearchParams(window.location.search).get("shop");

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