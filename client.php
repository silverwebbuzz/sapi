<?php
require_once 'config.php';
require_once 'db.php';
require_once 'shopify/shopify_functions.php';

// Verify HMAC
$installparams = $params = $_GET;

if (!verifyHmac($params, SHOPIFY_API_SECRET)) die('Invalid HMAC');

$shop = $params['shop'];

// Check if installed
$conn = DB::getInstance();
$stmt = $conn->prepare("SELECT status FROM stores WHERE shop = ?");
$stmt->bind_param("s", $shop);
$stmt->execute();
$result = $stmt->get_result();
$store = $result->fetch_assoc();

// If store does not exist or was uninstalled, restart installation
if (!$store || $store['status'] === 'uninstalled') {
    $install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, SHOPIFY_APP_URL . '/shopify/callback');
    header("Location: $install_url");
    exit();
}

// Set security headers for embedded app
header("Content-Security-Policy: frame-ancestors https://*.shopify.com https://admin.shopify.com");
echo "<h1>Welcome to SWB Auto PDF Invoices</h1>";

//plan display
$plans = $conn->query("SELECT * FROM plans")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Plans</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/styles.css">
</head>
<body>

<div class="pricing-container">
    <h2>Choose the right plan for your store</h2>
    <p>All plans come with a 7-day free trial.</p>
    
    <div class="pricing-grid">
        <?php foreach ($plans as $plan) : ?>
            <div class="pricing-card">
                <h3><?= htmlspecialchars($plan['name']) ?></h3>
                <p class="price">$<?= number_format($plan['price'], 2) ?>/month</p>
                <p class="orders"><?= $plan['order_limit'] ?> Orders per month</p>
                <p class="description"><?= htmlspecialchars($plan['description']) ?></p>
                <form action="subscribe.php" method="POST">
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    <button type="submit">Start Free Trial</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>