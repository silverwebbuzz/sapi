<?php include 'header.php';
include 'nav.php'; 

//Fetch Plans
//$plans_query = $conn->query("SELECT * FROM `plans` where price != '0.00'  ORDER BY id");

//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
$invoices_query = DBHelper::select("SELECT * FROM `$invoice_table` ORDER BY created_at DESC","",[]);
?>

<main class="main-content">

    <!-- Subscription Package -->
    
    <div class="package-card">
        <h3>Current Subscription Package</h3>
        <div class="package-details">
            <div class="package-info">
                <div class="package-name"><?= htmlspecialchars($currentPlan['plan_name'] ?? 'No active plan') ?></div>
                <div class="package-price">
                    $<?= htmlspecialchars($currentPlan['price'] ?? '0.00') ?>
                    /<?= ($currentPlan['billing_interval'] === 'annual') ? 'year' : 'month' ?>
                </div>
                <div class="package-stat">
                    <span><?= htmlspecialchars($currentPlan['terms'] ?? 'Automated PDF invoices'); ?></span>
                    <div class="package-stats">
                        <a href="change-plan" class="upgrade-btn">Upgrade Plan</a>
                    </div>
                </div>
            </div>
            <div class="package-stats">
                <div class="package-stat">
                    <span>Billing Cycle</span>
                    <strong>
                        <?= ucfirst(str_replace("_"," ",$currentPlan['billing_interval']) ?? 'month') ?>
                    </strong>
                </div>
                <div class="package-stat">
                    <span>Next Billing Date</span>
                    <strong><?= date('M d, Y', strtotime($currentPlan['current_period_end'] ?? '')) ?></strong>
                </div>
                <div class="package-stat">
                    <span>PDF Invoice Limit</span>
                    <strong>
                        <?= htmlspecialchars($currentPlan['order_limit'] ?? '0') ?>
                        /<?= ($currentPlan['billing_interval'] === 'annual') ? 'year' : 'month' ?>
                    </strong>
                </div>
                <div class="package-stat">
                    <span>Email Sent Limit</span>
                    <strong>
                        <?= htmlspecialchars($currentPlan['email_limit'] ?? '0') ?>
                        /<?= ($currentPlan['billing_interval'] === 'annual') ? 'year' : 'month' ?>
                    </strong>
                </div>
            </div>
            <div class="package-usage">
                <div class="usage-info">
                    <span>Invoice Used: <?= htmlspecialchars($currentPlan['order_used'] ?? '0') ?></span>
                    <span>Invoice Remaining: <?= (htmlspecialchars($currentPlan['order_limit'] ?? '0') - htmlspecialchars($currentPlan['order_used'] ?? '0')) ?></span>
                </div>
                <div class="usage-bar">
                    <?php 
                    $percentageUsed = 0;
                    if ($currentPlan['order_limit'] > 0) {
                        $percentageUsed = ($currentPlan['order_used'] / $currentPlan['order_limit']) * 100;
                        $percentageUsed = min(100, round($percentageUsed, 2));
                    }
                    ?>
                    <div class="usage-progress" style="width: <?= $percentageUsed ?>%"></div>
                </div>
                
                <br/>
                
                <div class="usage-info">
                    <span>Email Used: <?= htmlspecialchars($currentPlan['email_used'] ?? '0') ?></span>
                    <span>Email Remaining: <?= (htmlspecialchars($currentPlan['email_limit'] ?? '0') - htmlspecialchars($currentPlan['email_used'] ?? '0')) ?></span>
                </div>
                <div class="usage-bar">
                    <?php 
                    $percentageUsedemail = 0;
                    if ($currentPlan['email_limit'] > 0) {
                        $percentageUsedemail = ($currentPlan['email_used'] / $currentPlan['email_limit']) * 100;
                        $percentageUsedemail = min(100, round($percentageUsedemail, 2));
                    }
                    ?>
                    <div class="usage-progress" style="width: <?= $percentageUsedemail ?>%"></div>
                </div>
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
                        <?php if ($invoice['pdf_invoice']!=''){ ?>
                            <a href="#" class="view-invoice-btn" data-invoice-id="<?= $invoice['pdf_invoice']; ?>">View Invoice</a>
                            <?php if($send_email_upgrade_plan_button!='') echo $send_email_upgrade_plan_button; else { ?>
                            <a href="#" class="email-btn" onclick="sendEmail(<?= $shop_id ?>, <?= $invoice['order_id'] ?>, '<?= $invoice['email_status'] ?>'); return false;">
                            <?= ($invoice['email_status'] == 'pending') ? 'Send Email' : 'Resend Email'; ?>
                            </a>
                            <?php } ?>
                        <?php } else { 
                            if($gen_invoice_upgrade_plan_button!='') echo $gen_invoice_upgrade_plan_button; else {
                            ?>
                            <a href="#" class="gen-invoice-btn" onclick="generateInvoice(<?= $shop_id ?>, <?= $invoice['order_id'] ?>, '<?= $invoice['invoice_status'] ?>'); return false;" class="view-invoice">
                            <?= ($invoice['invoice_status'] == 'pending') ? 'Generate Invoice' : 'View Invoice'; ?>
                            </a>
                        <?php } } ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<!-- Message Box-->
<div id="message-box" style="display:none; position:fixed; top:20px; right:20px; padding:10px 20px; border-radius:5px; color:#fff; z-index:9999; font-weight:bold;"></div>

<!-- Modal Wrapper -->
<div id="invoiceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999;">
  <div style="position:relative; width:80%; max-width:800px; margin:5% auto; background:#fff; padding:20px;">
    <span style="position:absolute; top:10px; right:20px; cursor:pointer;" onclick="closeInvoiceModal()">✖</span>
    <embed id="invoiceFrame" type="application/pdf" style="width:100%; height:600px; border:none;" />
  </div>
</div>
<?php include 'footer.php'; ?>