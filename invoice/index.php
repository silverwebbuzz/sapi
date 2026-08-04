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
    
    <?php
    // "/month" vs "/year" is one translated unit, not a slash plus a word,
    // so languages that inflect or reorder it stay correct.
    $isAnnual   = ($currentPlan['billing_interval'] === 'annual');
    $periodKey  = $isAnnual ? 'common.per_year' : 'common.per_month';
    ?>
    <div class="package-card">
        <h3><?= e('dashboard.subscription_title') ?></h3>
        <div class="package-details">
            <div class="package-info">
                <div class="package-name"><?= htmlspecialchars($currentPlan['plan_name'] ?? t('dashboard.no_active_plan')) ?></div>
                <div class="package-price">
                    <?= htmlspecialchars(fmt_currency($currentPlan['price'] ?? 0, $currentPlan['currency'] ?? 'USD')) ?><?= e($periodKey) ?>
                </div>
                <div class="package-stat">
                    <span><?= htmlspecialchars($currentPlan['terms'] ?? t('app.tagline')); ?></span>
                    <div class="package-stats">
                        <a href="change-plan?shop=<?php echo $shop; ?>" class="upgrade-btn"><?= e('common.upgrade_plan') ?></a>
                    </div>
                </div>
            </div>
            <div class="package-stats">
                <div class="package-stat">
                    <span><?= e('dashboard.billing_cycle') ?></span>
                    <strong>
                        <?= e($isAnnual ? 'common.year' : 'common.month') ?>
                    </strong>
                </div>
                <div class="package-stat">
                    <span><?= e('dashboard.next_billing_date') ?></span>
                    <strong><?= !empty($currentPlan['current_period_end']) ? htmlspecialchars(fmt_date($currentPlan['current_period_end'])) : e('common.not_available') ?></strong>
                </div>
                <div class="package-stat">
                    <span><?= e('dashboard.pdf_invoice_limit') ?></span>
                    <strong>
                        <?= htmlspecialchars(fmt_number((int)($currentPlan['order_limit'] ?? 0))) ?><?= e($periodKey) ?>
                    </strong>
                </div>
                <div class="package-stat">
                    <span><?= e('dashboard.email_sent_limit') ?></span>
                    <strong>
                        <?= htmlspecialchars(fmt_number((int)($currentPlan['email_limit'] ?? 0))) ?><?= e($periodKey) ?>
                    </strong>
                </div>
            </div>
            <div class="package-usage">
                <div class="usage-info">
                    <span><?= e('dashboard.invoice_used', ['count' => fmt_number((int)($currentPlan['order_used'] ?? 0))]) ?></span>
                    <span><?= e('dashboard.invoice_remaining', ['count' => fmt_number(max(0, (int)($currentPlan['order_limit'] ?? 0) - (int)($currentPlan['order_used'] ?? 0)))]) ?></span>
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
                    <span><?= e('dashboard.email_used', ['count' => fmt_number((int)($currentPlan['email_used'] ?? 0))]) ?></span>
                    <span><?= e('dashboard.email_remaining', ['count' => fmt_number(max(0, (int)($currentPlan['email_limit'] ?? 0) - (int)($currentPlan['email_used'] ?? 0)))]) ?></span>
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
        <h3><?= e('dashboard.recent_orders') ?></h3>
        <table id="ordersTable" class="display">
        <thead>
                <tr>
                    <th><?= e('table.order_id') ?></th>
                    <th><?= e('table.date') ?></th>
                    <th><?= e('table.customer') ?></th>
                    <th><?= e('table.amount') ?></th>
                    <th><?= e('table.invoice') ?></th>
                    <th><?= e('table.email') ?></th>
                    <th><?= e('table.action') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach($invoices_query as $invoice) :
            //while ($invoice = $invoices_query->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($invoice['order_name']) ?></td>
                    <td data-order="<?= htmlspecialchars($invoice['created_at']) ?>"><?= htmlspecialchars(fmt_date($invoice['created_at'])) ?></td>
                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    <td><?= htmlspecialchars(fmt_currency($invoice['total_price'], $invoice['currency'])) ?></td>
                    <td><span class="status <?= htmlspecialchars($invoice['invoice_status']) ?>"><?= htmlspecialchars(t_status($invoice['invoice_status'])) ?></span></td>
                    <td><span class="status <?= htmlspecialchars($invoice['email_status']) ?>"><?= htmlspecialchars(t_status($invoice['email_status'])) ?></span></td>
                    <td>
                        <?php if ($invoice['pdf_invoice'] != ''): ?>
                            <a href="#" class="view-invoice-btn"
                               data-invoice-id="<?= htmlspecialchars($invoice['pdf_invoice']) ?>"><?= e('actions.view_invoice') ?></a>
                            <?php if ($send_email_upgrade_plan_button != ''): ?>
                                <?= $send_email_upgrade_plan_button ?>
                            <?php else: ?>
                                <a href="#" class="email-btn js-send-email"
                                   data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                                   data-order-id="<?= htmlspecialchars($invoice['order_id']) ?>"
                                   data-email-status="<?= htmlspecialchars($invoice['email_status']) ?>">
                                    <?= e($invoice['email_status'] == 'pending' ? 'actions.send_email' : 'actions.resend_email') ?>
                                </a>
                                <a href="#" class="email-btn owner js-send-email-owner"
                                   data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                                   data-order-id="<?= htmlspecialchars($invoice['order_id']) ?>">
                                    <?= e('actions.send_to_store_owner') ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($gen_invoice_upgrade_plan_button != ''): ?>
                                <?= $gen_invoice_upgrade_plan_button ?>
                            <?php else: ?>
                                <a href="#" class="gen-invoice-btn js-generate-invoice"
                                   data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                                   data-order-id="<?= htmlspecialchars($invoice['order_id']) ?>"
                                   data-invoice-status="<?= htmlspecialchars($invoice['invoice_status']) ?>">
                                    <?= e($invoice['invoice_status'] == 'pending' ? 'actions.generate_invoice' : 'actions.view_invoice') ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
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