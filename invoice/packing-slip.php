<?php
include 'header.php';
include 'nav.php';

$isFreePlan = ((float)($currentPlan['price'] ?? 0) == 0.00);

$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));

// Defensive check: if the migration hasn't run yet on this store, the
// packing_slip_* columns won't exist. Surface a friendly message instead
// of letting MySQL throw a column-not-found error.
$cols = DBHelper::select("SHOW COLUMNS FROM `$invoice_table`", "", []);
$colNames = array_column($cols ?: [], 'Field');
$migrated = in_array('packing_slip_pdf', $colNames, true)
         && in_array('packing_slip_status', $colNames, true);

$orders = [];
if ($migrated) {
    $orders = DBHelper::select(
        "SELECT order_id, order_number, order_name, customer_name, currency, total_price,
                created_at, packing_slip_status, packing_slip_pdf
           FROM `$invoice_table`
          ORDER BY created_at DESC",
        "",
        []
    );
}

// Cap and quota are independent of the order-quota for packing slips.
$BULK_BATCH_CAP = 50;
?>

<main class="main-content">
    <div class="page-header">
        <h2><?= e('packing.title') ?></h2>
        <h3><?= e('packing.subtitle') ?></h3>
    </div>

    <?php if (!$migrated): ?>
        <div class="bulk-upgrade-banner" style="background:#fef2f2; border-color:#fca5a5;">
            <div>
                <strong style="color:#991b1b;"><?= e('packing.setup_required_title') ?></strong>
                <p style="color:#7f1d1d;">
                    <?= e('packing.setup_required_body') ?>
                </p>
            </div>
        </div>
    <?php elseif ($isFreePlan): ?>
        <div class="bulk-upgrade-banner">
            <div>
                <strong><?= e('packing.paid_feature_title') ?></strong>
                <p><?= e('packing.paid_feature_body') ?></p>
            </div>
            <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn"><?= e('common.upgrade_plan') ?></a>
        </div>
    <?php endif; ?>

    <?php if ($migrated): ?>
    <div class="orders-card">
        <div class="bulk-toolbar">
            <label class="bulk-select-all">
                <input type="checkbox" id="ps-select-all" <?= $isFreePlan ? 'disabled' : '' ?>>
                <span><?= e('common.select_all_page') ?></span>
            </label>
            <span class="bulk-selected-count" id="ps-selected-count"><?= e('common.selected_count', ['count' => fmt_number(0)]) ?></span>

            <?php if ($isFreePlan): ?>
                <button type="button" class="bulk-download-btn locked" disabled>
                    <span class="lock-icon">&#128274;</span> <?= e('packing.bulk_generate') ?>
                </button>
                <button type="button" class="bulk-download-btn locked" disabled>
                    <span class="lock-icon">&#128274;</span> <?= e('common.download_zip') ?>
                </button>
                <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn"><?= e('common.upgrade_to_unlock') ?></a>
            <?php else: ?>
                <button type="button" class="bulk-download-btn" id="ps-bulk-generate-btn"
                        data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                        data-batch-cap="<?= (int)$BULK_BATCH_CAP ?>"
                        disabled>
                    <?= e('orders.bulk_generate') ?>
                </button>
                <form id="ps-bulk-zip-form" method="post" action="bulk-download-zip" target="_blank" style="display: inline;">
                    <input type="hidden" name="shop_id" value="<?= htmlspecialchars($shop_id) ?>">
                    <input type="hidden" name="type" value="packing_slip">
                    <button type="submit" class="bulk-download-btn" id="ps-bulk-zip-btn" disabled>
                        <?= e('common.download_zip') ?>
                    </button>
                </form>
                <span class="bulk-hint"><?= e('packing.quota_hint') ?></span>
            <?php endif; ?>
        </div>

        <table id="packingSlipsTable" class="display">
            <thead>
                <tr>
                    <th style="width: 40px;">&nbsp;</th>
                    <th><?= e('table.order_id') ?></th>
                    <th><?= e('table.date') ?></th>
                    <th><?= e('table.customer') ?></th>
                    <th><?= e('table.amount') ?></th>
                    <th><?= e('table.status') ?></th>
                    <th><?= e('table.action') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <?php
                    $hasSlip = !empty($o['packing_slip_pdf']);
                    $status  = $hasSlip ? 'generated' : 'pending';
                ?>
                <tr>
                    <td>
                        <input
                            type="checkbox"
                            class="ps-row-check"
                            value="<?= htmlspecialchars($o['order_id']) ?>"
                            data-order-label="<?= htmlspecialchars($o['order_name'] ?? ('#' . $o['order_number'])) ?>"
                            data-has-slip="<?= $hasSlip ? '1' : '0' ?>"
                            data-target-form="ps-bulk-zip-form"
                            <?= $isFreePlan ? 'disabled' : '' ?>
                        >
                    </td>
                    <td><?= htmlspecialchars($o['order_name'] ?? ('#' . $o['order_number'])) ?></td>
                    <td data-order="<?= htmlspecialchars($o['created_at']) ?>"><?= htmlspecialchars(fmt_date($o['created_at'])) ?></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= htmlspecialchars(fmt_currency($o['total_price'], $o['currency'])) ?></td>
                    <td><span class="status <?= $status ?>"><?= htmlspecialchars(t_status($status)) ?></span></td>
                    <td>
                        <?php if ($hasSlip): ?>
                            <a href="#" class="view-invoice-btn js-view-packing-slip"
                               data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                               data-order-id="<?= htmlspecialchars($o['order_id']) ?>"><?= e('actions.view_slip') ?></a>
                            <?php if (!$isFreePlan): ?>
                                <a href="#" class="gen-invoice-btn js-generate-packing-slip"
                                   data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                                   data-order-id="<?= htmlspecialchars($o['order_id']) ?>"><?= e('actions.regenerate') ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($isFreePlan): ?>
                                <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="gen-invoice-btn"><?= e('actions.upgrade_to_generate') ?></a>
                            <?php else: ?>
                                <a href="#" class="gen-invoice-btn js-generate-packing-slip"
                                   data-shop-id="<?= htmlspecialchars($shop_id) ?>"
                                   data-order-id="<?= htmlspecialchars($o['order_id']) ?>"><?= e('actions.generate_packing_slip') ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<!-- Message Box -->
<div id="message-box" style="display:none; position:fixed; top:20px; right:20px; padding:10px 20px; border-radius:5px; color:#fff; z-index:9999; font-weight:bold;"></div>

<!-- Inline preview modal (reuses the invoice modal markup pattern) -->
<div id="invoiceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999;">
  <div style="position:relative; width:80%; max-width:800px; margin:5% auto; background:#fff; padding:20px;">
    <span style="position:absolute; top:10px; right:20px; cursor:pointer;" onclick="closeInvoiceModal()">✖</span>
    <embed id="invoiceFrame" type="application/pdf" style="width:100%; height:600px; border:none;" />
  </div>
</div>

<!-- Bulk progress modal (shared) -->
<div id="bulkProgressModal" class="bulk-progress-modal" style="display:none;" role="dialog" aria-modal="true">
    <div class="bulk-progress-card">
        <h3 id="bulkProgressTitle"><?= e('bulk.generating_slips_title') ?></h3>
        <p class="bulk-progress-status" id="bulkProgressStatus"><?= e('bulk.preparing') ?></p>
        <div class="bulk-progress-bar">
            <div class="bulk-progress-bar-fill" id="bulkProgressFill" style="width: 0%"></div>
        </div>
        <p class="bulk-progress-meta" id="bulkProgressMeta"><?= e('bulk.keep_tab_open_slips') ?></p>
        <div class="bulk-progress-actions">
            <button type="button" class="bulk-progress-cancel" id="bulkProgressCancel"><?= e('bulk.stop_after_current') ?></button>
            <button type="button" class="bulk-progress-close" id="bulkProgressClose" style="display:none;"><?= e('common.close') ?></button>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
