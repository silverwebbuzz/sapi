<?php include 'header.php';
include 'nav.php'; 

//Fetch Plans
$plans_query = $conn->query("SELECT * FROM `plans` where price != '0.00'  ORDER BY id");

//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));
$invoices_query = $conn->query("SELECT * FROM `$invoice_table` ORDER BY created_at DESC");
?>

<main class="main-content">
    <!-- Stats Cards -->
    <div class="stats-row">
    <?php while ($plan = $plans_query->fetch_assoc()): ?>
        <div class="stat-card">
            <div class="stat-title"><?= $plan['name'] ?> (Monhtly)</div>
            <div class="stat-value">$<?= $plan['price'] ?></div>
            <div class="package-stat">
                    <span>PDF Invoice Limit : <?= $plan['order_limit'] ?> </span>
            </div>
            <div class="package-stat">
                    <span>Email Sent Limit: <?= $plan['email_limit'] ?></span>
            </div>
            <div class="stat-trend up"><?= $plan['description'] ?></div>
            <div><button class="btn-upgrade">Upgrade Plan</button></div>
        </div>
    <?php endwhile; ?>
    </div>

    <!-- Subscription Package -->
    <?php 
    //Fetch store plan.
    $sql = "
        SELECT 
            ss.store_id,
            ss.plan_id,
            ss.order_limit AS subscription_order_limit,
            ss.features AS subscription_features,
            ss.start_date,
            ss.end_date,
            ss.status,
            p.name AS plan_name,
            p.price,
            p.order_limit AS plan_order_limit,
            p.email_limit,
            p.features AS plan_features,
            p.description
        FROM store_subscriptions ss
        JOIN plans p ON ss.plan_id = p.id
        WHERE ss.store_id = ? AND ss.status = 'active'
        ORDER BY ss.start_date DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $shop_id);
    $stmt->execute();
    $curr_result = $stmt->get_result();
    $currentPlan = $curr_result->fetch_assoc();
        /*echo "<h2>Current Plan for Store ID {$currentPlan['store_id']}</h2>";
        echo "<p>Plan Name: " . htmlspecialchars($currentPlan['plan_name']) . "</p>";
        echo "<p>Price: $" . htmlspecialchars($currentPlan['price']) . "</p>";
        echo "<p>Order Limit (Plan): " . htmlspecialchars($currentPlan['plan_order_limit']) . "</p>";
        echo "<p>Email Limit: " . htmlspecialchars($currentPlan['email_limit']) . "</p>";
        echo "<p>Description: " . htmlspecialchars($currentPlan['description']) . "</p>";
        echo "<p>Subscription Order Limit: " . htmlspecialchars($currentPlan['subscription_order_limit']) . "</p>";
        echo "<p>Subscription Status: " . htmlspecialchars($currentPlan['status']) . "</p>";
        echo "<p>Start Date: " . htmlspecialchars($currentPlan['start_date']) . "</p>";
        echo "<p>End Date: " . htmlspecialchars($currentPlan['end_date']) . "</p>";*/
   
    ?>
    <div class="package-card">
        <h3>Current Subscription Package</h3>
        <div class="package-details">
            <div class="package-info">
                <div class="package-name"><?= htmlspecialchars($currentPlan['plan_name'])?></div>
                <div class="package-price">$<?= htmlspecialchars($currentPlan['price'])?>/month</div>
                <div class="package-stat"><span><?= htmlspecialchars($currentPlan['description']); ?></span></div>
            </div>
            <div class="package-stats">
                <div class="package-stat">
                    <span>Billing Cycle</span>
                    <strong>1 Month</strong>
                </div>
                <div class="package-stat">
                    <span>Next Billing Date</span>
                    <strong><?= htmlspecialchars($currentPlan['end_date'])?></strong>
                </div>
                <div class="package-stat">
                    <span>PDF Invoice Limit</span>
                    <strong><?= htmlspecialchars($currentPlan['plan_order_limit'])?>/month</strong>
                </div>
                <div class="package-stat">
                    <span>Email Sent Limit</span>
                    <strong><?= htmlspecialchars($currentPlan['email_limit'])?>/month</strong>
                </div>
            </div>
            <div class="package-usage">
                <div class="usage-info">
                    <span>Used: 10</span>
                    <span>Remaining: 10</span>
                </div>
                <div class="usage-bar">
                    <div class="usage-progress" style="width: 50%"></div>
                </div>
            </div>
            <button class="btn-upgrade">Upgrade Plan</button>
            <button class="btn-cancel">Cancel Plan</button>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="orders-card">
        <h3>Recent Orders</h3>
        <table id="ordersTable" class="display">
        <thead>
                <tr>
                    <th>ORDER ID</th>
                    <th>DATE</th>
                    <th>CUSTOMER</th>
                    <th>AMOUNT</th>
                    <th>INVOICE</th>
                    <th>EMAIL</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($invoice = $invoices_query->fetch_assoc()): ?>
                <tr>
                    <td><?= $invoice['order_number'] ?></td>
                    <td><?= $invoice['created_at'] ?></td>
                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    <td><?= $invoice['currency']?> <?= number_format($invoice['total_price'], 2) ?></td>
                    <td><span class="status completed"><?= ucfirst($invoice['invoice_status']) ?></span></td>
                    <td><span class="status completed"><?= ucfirst($invoice['email_status']) ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>/invoice/generatepdf.php?shop_id=<?= $shop_id ?>&order_id=<?= $invoice['order_id'] ?>&invoicestatus=<?= $invoice['invoice_status'] ?>" class="view-invoice"><?= ($invoice['invoice_status'] == 'pending') ? 'Generate Invoice' : 'View Invoice';?></a>
                    <br/><a href="<?= BASE_URL ?>/invoice/sendemail.php?shop_id=<?= $shop_id ?>&order_id=<?= $invoice['order_id'] ?>&emailstatus=<?= $invoice['email_status'] ?>" class="view-invoice"><?= ($invoice['email_status'] == 'pending') ? 'Send Email' : 'Resend Email';?></a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'footer.php'; ?>