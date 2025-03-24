<?php
require_once 'config.php';
require_once 'db.php';
require_once 'shopify/shopify_functions.php';

$plans = $conn->query("SELECT * FROM plans")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Plans</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
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