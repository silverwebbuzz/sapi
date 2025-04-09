<?php include 'header.php';
include 'nav.php'; 

//Fetch Plans
//$plans_query = $conn->query("SELECT * FROM `plans` where price != '0.00'  ORDER BY id");


//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
//$invoices_query = $conn->query("SELECT * FROM `$invoice_table` ORDER BY created_at DESC");

$invoices_query = DBHelper::select("SELECT * FROM `$invoice_table` ORDER BY created_at DESC","",[]);
?>

<main class="main-content">

    <!-- Subscription Package -->
    
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
                    <strong><?= htmlspecialchars($currentPlan['cancelled_on'])?></strong>
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
                    <span>Invoice Used: <?= htmlspecialchars($currentPlan['order_used'])?></span>
                    <span>Invoice Remaining: <?= (htmlspecialchars($currentPlan['plan_order_limit']) - htmlspecialchars($currentPlan['order_used']))?></span>
                </div>
                <div class="usage-bar">
                    <?php 
                    // Calculate percentage
                        $percentageUsed = ($currentPlan['order_used'] / $currentPlan['plan_order_limit']) * 100;
                        // Optionally round the value
                        $percentageUsed = round($percentageUsed, 2); 
                    ?>
                    <div class="usage-progress" style="width: <?= $percentageUsed;?>%"></div>
                </div>
                <br/>
                <div class="usage-info">
                    <span>Email Used: <?= htmlspecialchars($currentPlan['email_used'])?></span>
                    <span>Email Remaining: <?= (htmlspecialchars($currentPlan['email_limit']) - htmlspecialchars($currentPlan['email_used']))?></span>
                </div>
                <div class="usage-bar">
                <?php 
                    // Calculate percentage
                        $percentageUsedemail = ($currentPlan['email_used'] / $currentPlan['email_limit']) * 100;
                        // Optionally round the value
                        $percentageUsedemail = round($percentageUsedemail, 2); 
                    ?>
                    <div class="usage-progress" style="width: <?= $percentageUsedemail;?>%"></div>
                </div>
            </div>
            <div class="package-stats">
                <button class="btn-upgrade">Upgrade Plan</button>
                <button class="btn-cancel">Cancel Plan</button>
            </div>
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
            <?php
            foreach($invoices_query as $invoice) :
            //while ($invoice = $invoices_query->fetch_assoc()): ?>
                <tr>
                    <td><?= $invoice['order_name'] ?></td>
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
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include 'footer.php'; ?>