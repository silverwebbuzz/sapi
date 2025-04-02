<?php include 'header.php';
include 'nav.php'; 

//Fetch Plans
$plans_query = $conn->query("SELECT * FROM `plans` ORDER BY id");

//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));
$invoices_query = $conn->query("SELECT * FROM `$invoice_table` ORDER BY created_at DESC");
?>

<main class="main-content">
    <!-- Stats Cards -->
    <div class="stats-row">
    <?php while ($plan = $plans_query->fetch_assoc()): ?>
        <div class="stat-card">
            <div class="stat-title"><?= $plan['name'] ?></div>
            <div class="stat-value">$<?= $plan['price'] ?>/month</div>
            <div class="package-stat">
                    <span>Invoice</span>
                    <strong><?= $plan['order_limit'] ?>/month</strong>
            </div>
            <div class="package-stat">
                    <span>Email</span>
                    <strong><?= $plan['email_limit'] ?>/month</strong>
            </div>
            <div class="stat-trend up"><?= $plan['description'] ?></div>
            <button class="btn-upgrade">Upgrade Plan</button>
        </div>
    <?php endwhile; ?>
        <div class="stat-card">
            <div class="stat-title">Conversion Rate</div>
            <div class="stat-value">28.5%</div>
            <div class="stat-trend up">↑ 2.5% from last month</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Sales This Month</div>
            <div class="stat-value">$28,450</div>
            <div class="stat-trend up">↑ $4,210 from last month</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Sales Overview</div>
            <div class="stat-value">$42.5k</div>
            <div class="stat-trend down">↓ $1,150 from last month</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Visits</div>
            <div class="stat-value">12,749</div>
            <div class="stat-trend up">↑ 25.5% from last month</div>
        </div>
    </div>

    <!-- Subscription Package -->
    <div class="package-card">
        <h3>Subscription Package</h3>
        <div class="package-details">
            <div class="package-info">
                <div class="package-name">Basic Plan</div>
                <div class="package-price">$9.99/month</div>
            </div>
            <div class="package-stats">
                <div class="package-stat">
                    <span>Billing Cycle</span>
                    <strong>1 Month</strong>
                </div>
                <div class="package-stat">
                    <span>Next Billing Date</span>
                    <strong>2025-04-28</strong>
                </div>
                <div class="package-stat">
                    <span>Invoices Allowed</span>
                    <strong>300/month</strong>
                </div>
            </div>
            <div class="package-usage">
                <div class="usage-info">
                    <span>Used: 100</span>
                    <span>Remaining: 200</span>
                </div>
                <div class="usage-bar">
                    <div class="usage-progress" style="width: 33.33%"></div>
                </div>
            </div>
            <button class="btn-upgrade">Upgrade Plan</button>
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