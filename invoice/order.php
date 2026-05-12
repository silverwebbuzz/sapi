<?php include 'header.php';
include 'nav.php';

// Same paid-plan signal used elsewhere in the app
$isFreePlan = ((float)($currentPlan['price'] ?? 0) == 0.00);

// Quota headroom (paid plan only) — used to cap the bulk action.
$ordersRemaining = 0;
if (!$isFreePlan && isset($currentPlan['order_limit'], $currentPlan['order_used'])) {
    $ordersRemaining = max(0, (int)$currentPlan['order_limit'] - (int)$currentPlan['order_used']);
}

// Per-batch cap (also enforced client- and server-side)
$BULK_BATCH_CAP = 50;

//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
$invoices_query = DBHelper::select("SELECT * FROM `$invoice_table` ORDER BY created_at DESC","",[]);
?>

<main class="main-content">
    <div class="page-header">
        <h2>Shopify Orders List</h2>
        <h3>Display All Shopify Orders with all invoice and email status.</h3>
    </div>

    <div class="orders-card">
        <div class="bulk-toolbar">
            <label class="bulk-select-all">
                <input type="checkbox" id="orders-select-all" <?= $isFreePlan ? 'disabled' : '' ?>>
                <span>Select all on this page</span>
            </label>
            <span class="bulk-selected-count" id="orders-selected-count">0 selected</span>
            <?php if ($isFreePlan): ?>
                <button type="button" class="bulk-download-btn locked" disabled>
                    <span class="lock-icon">&#128274;</span> Bulk Generate / Regenerate
                </button>
                <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn">Upgrade to unlock</a>
            <?php else: ?>
                <button type="button" class="bulk-download-btn" id="bulk-generate-btn"
                        data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                        data-batch-cap="<?= (int)$BULK_BATCH_CAP ?>"
                        data-orders-remaining="<?= (int)$ordersRemaining ?>"
                        disabled>
                    Bulk Generate / Regenerate
                </button>
                <span class="bulk-hint">Up to <?= (int)$BULK_BATCH_CAP ?> per batch · <?= (int)$ordersRemaining ?> remaining in plan</span>
            <?php endif; ?>
        </div>

        <table id="ordersTable" class="display">
            <thead>
                <tr>
                    <th style="width: 40px;">&nbsp;</th>
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
            <?php foreach($invoices_query as $invoice) : ?>
                <tr>
                    <td>
                        <input
                            type="checkbox"
                            class="orders-row-check"
                            value="<?= htmlspecialchars($invoice['order_id']) ?>"
                            data-order-label="<?= htmlspecialchars($invoice['order_name'] ?? ('#' . $invoice['order_number'])) ?>"
                            <?= $isFreePlan ? 'disabled' : '' ?>
                        >
                    </td>
                    <td><?= htmlspecialchars($invoice['order_number']) ?></td>
                    <td><?= htmlspecialchars($invoice['created_at']) ?></td>
                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    <td><?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float)$invoice['total_price'], 2) ?></td>
                    <td><span class="status <?= htmlspecialchars($invoice['invoice_status']) ?>"><?= ucfirst($invoice['invoice_status']) ?></span></td>
                    <td><span class="status <?= htmlspecialchars($invoice['email_status']) ?>"><?= ucfirst($invoice['email_status']) ?></span></td>
                    <td>
                        <?php if ($invoice['pdf_invoice'] != ''): ?>
                            <a href="#" class="view-invoice-btn" data-invoice-id="<?= htmlspecialchars($invoice['pdf_invoice']) ?>">View Invoice</a>
                            <a href="#" class="gen-invoice-btn js-generate-invoice"
                               data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                               data-order-id="<?= htmlspecialchars($invoice['order_id']) ?>"
                               data-invoice-status="Pending">Re-Generate Invoice</a>
                            <br>
                            <?php if ($send_email_upgrade_plan_button != ''): ?>
                                <?= $send_email_upgrade_plan_button ?>
                            <?php else: ?>
                                <a href="#" class="email-btn js-send-email"
                                   data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                                   data-order-id="<?= htmlspecialchars($invoice['order_id']) ?>"
                                   data-email-status="<?= htmlspecialchars($invoice['email_status']) ?>">
                                    <?= ($invoice['email_status'] == 'pending') ? 'Send Email' : 'Resend Email' ?>
                                </a>
                                <a href="#" class="email-btn owner js-send-email-owner"
                                   data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                                   data-order-id="<?= htmlspecialchars($invoice['order_id']) ?>">
                                    Send to Store Owner
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
                                    <?= ($invoice['invoice_status'] == 'pending') ? 'Generate Invoice' : 'View Invoice' ?>
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

<!-- Bulk progress modal -->
<div id="bulkProgressModal" class="bulk-progress-modal" style="display:none;" role="dialog" aria-labelledby="bulkProgressTitle" aria-modal="true">
    <div class="bulk-progress-card">
        <h3 id="bulkProgressTitle">Generating invoices…</h3>
        <p class="bulk-progress-status" id="bulkProgressStatus">Preparing…</p>
        <div class="bulk-progress-bar">
            <div class="bulk-progress-bar-fill" id="bulkProgressFill" style="width: 0%"></div>
        </div>
        <p class="bulk-progress-meta" id="bulkProgressMeta">Please keep this tab open while we process your invoices.</p>
        <div class="bulk-progress-actions">
            <button type="button" class="bulk-progress-cancel" id="bulkProgressCancel">Stop after current</button>
            <button type="button" class="bulk-progress-close" id="bulkProgressClose" style="display:none;">Close</button>
        </div>
    </div>
</div>
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