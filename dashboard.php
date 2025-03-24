<?php
require_once 'config.php';
require_once 'db.php';

// Check if store_id is provided
if (!isset($_GET['shop_id'])) {
    die("Invalid request. Shop ID missing.");
}

$shop_id = intval($_GET['shop_id']);
$conn = DB::getInstance();

// Fetch Store Information
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$store_result = $stmt->get_result();

if ($store_result->num_rows === 0) {
    die("Store not found.");
}

$store = $store_result->fetch_assoc();

// Fetch Active Subscription
$stmt = $conn->prepare("
    SELECT s.*, p.name AS plan_name, p.order_limit, p.price
    FROM store_subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.store_id = ? AND s.status = 'active'
");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$subscription_result = $stmt->get_result();

$subscription = $subscription_result->num_rows > 0 ? $subscription_result->fetch_assoc() : null;


//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));

$invoices_query = $conn->query("SELECT * FROM `$invoice_table` ORDER BY created_at DESC LIMIT 10");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Dashboard</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Welcome, <?= htmlspecialchars($store['store_name']) ?></h1>
        <p><strong>Store Domain:</strong> <?= htmlspecialchars($store['shop']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($store['email']) ?></p>

        <h2>Subscription Details</h2>
        <?php if ($subscription): ?>
            <p><strong>Plan:</strong> <?= htmlspecialchars($subscription['plan_name']) ?> (<?= $subscription['order_limit'] ?> orders/month)</p>
            <p><strong>Price:</strong> $<?= number_format($subscription['price'], 2) ?>/month</p>
            <p><strong>Start Date:</strong> <?= $subscription['start_date'] ?></p>
            <p><strong>End Date:</strong> <?= $subscription['end_date'] ?></p>
            <p><strong>Status:</strong> <?= ucfirst($subscription['status']) ?></p>
            <a href="upgrade.php?shop_id=<?= $shop_id ?>" class="btn">Upgrade Plan</a>
        <?php else: ?>
            <p>You are not subscribed to any plan.</p>
            <a href="pricing.php?shop_id=<?= $shop_id ?>" class="btn">Choose a Plan</a>
        <?php endif; ?>
    </div>
    <h2>Recent Invoices</h2>
    <?php if ($invoices_query->num_rows > 0): ?>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Date</th>
                <th>Invoice</th>
            </tr>
            <?php while ($invoice = $invoices_query->fetch_assoc()): ?>
                <tr>
                    <td><?= $invoice['order_id'] ?></td>
                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    <td>$<?= number_format($invoice['total_price'], 2) ?></td>
                    <td><?= ucfirst($invoice['invoice_status']) ?></td>
                    <td><?= $invoice['created_at'] ?></td>
                    <td><?= BASE_URL ?>/generatepdf?shop_id=<?= $shop_id ?>&order_id<?= $invoice['order_id'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No invoices found.</p>
    <?php endif; ?>
</body>
</html>