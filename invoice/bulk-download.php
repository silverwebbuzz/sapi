<?php
include 'header.php';
include 'nav.php';

$isFreePlan = ((float)($currentPlan['price'] ?? 0) == 0.00);

// Which document type to bulk-download. Whitelist the value so users
// can't inject an arbitrary column name into the query.
$type = isset($_GET['type']) && $_GET['type'] === 'packing_slip' ? 'packing_slip' : 'invoice';

$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));

// Check whether packing-slip columns exist for this store. If not yet
// migrated, we still show the toggle but warn on the Packing Slips view.
$cols = DBHelper::select("SHOW COLUMNS FROM `$invoice_table`", "", []);
$colNames = array_column($cols ?: [], 'Field');
$packingSlipReady = in_array('packing_slip_pdf', $colNames, true)
                 && in_array('packing_slip_status', $colNames, true);

if ($type === 'packing_slip' && $packingSlipReady) {
    $generated = DBHelper::select(
        "SELECT order_id, order_number, order_name, customer_name, currency, total_price, created_at
           FROM `$invoice_table`
          WHERE packing_slip_status = 'generated'
            AND packing_slip_pdf IS NOT NULL
            AND packing_slip_pdf != ''
          ORDER BY created_at DESC",
        "",
        []
    );
    $heading      = t('bulk.packing_slips_title');
    $subheading   = t('bulk.packing_slips_subtitle');
    $generateHref = 'packing-slip?shop=' . htmlspecialchars($shop);
    $generateLabel = t('bulk.link_packing_slips');
    $emptyMessage = t('bulk.empty_packing_slips');
} else {
    if ($type === 'packing_slip' && !$packingSlipReady) {
        // Force the type back to invoice if migration hasn't run yet.
        $type = 'invoice';
    }
    $generated = DBHelper::select(
        "SELECT order_id, order_number, order_name, customer_name, currency, total_price, created_at
           FROM `$invoice_table`
          WHERE invoice_status = 'generated'
            AND pdf_invoice IS NOT NULL
            AND pdf_invoice != ''
          ORDER BY created_at DESC",
        "",
        []
    );
    $heading      = t('bulk.invoices_title');
    $subheading   = t('bulk.invoices_subtitle');
    $generateHref = 'index?shop=' . htmlspecialchars($shop);
    $generateLabel = t('bulk.link_dashboard');
    $emptyMessage = t('bulk.empty_invoices');
}
?>

<main class="main-content">
    <div class="page-header">
        <h2><?= htmlspecialchars($heading) ?></h2>
        <h3><?= htmlspecialchars($subheading) ?></h3>
    </div>

    <!-- Type toggle -->
    <div class="bulk-type-toggle">
        <a href="bulk-download?shop=<?= htmlspecialchars($shop) ?>&type=invoice"
           class="<?= $type === 'invoice' ? 'active' : '' ?>"><?= e('bulk.tab_invoices') ?></a>
        <a href="bulk-download?shop=<?= htmlspecialchars($shop) ?>&type=packing_slip"
           class="<?= $type === 'packing_slip' ? 'active' : '' ?>"><?= e('bulk.tab_packing_slips') ?></a>
    </div>

    <?php if ($type === 'packing_slip' && !$packingSlipReady): ?>
        <div class="bulk-upgrade-banner" style="background:#fef2f2; border-color:#fca5a5;">
            <div>
                <strong style="color:#991b1b;"><?= e('packing.not_enabled_title') ?></strong>
                <p style="color:#7f1d1d;"><?= e('packing.not_enabled_body') ?></p>
            </div>
        </div>
    <?php elseif ($isFreePlan): ?>
        <div class="bulk-upgrade-banner">
            <div>
                <strong><?= e('bulk.paid_feature_title') ?></strong>
                <p><?= e('bulk.paid_feature_body') ?></p>
            </div>
            <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn"><?= e('common.upgrade_plan') ?></a>
        </div>
    <?php endif; ?>

    <div class="orders-card">
        <?php if (empty($generated)): ?>
            <p style="padding: 24px; text-align: center; color: #6b7280; font-size: 14px;">
                <?= htmlspecialchars($emptyMessage) ?>
                <a href="<?= $generateHref ?>" style="color: #111827; text-decoration: underline;"><?= htmlspecialchars($generateLabel) ?></a>
                <?= e('bulk.empty_suffix') ?>
            </p>
        <?php else: ?>
            <form id="bulk-download-form" method="post" action="bulk-download-zip" target="_blank">
                <input type="hidden" name="shop_id" value="<?= htmlspecialchars($shop_id) ?>">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

                <div class="bulk-toolbar">
                    <label class="bulk-select-all">
                        <input type="checkbox" id="bulk-select-all" <?= $isFreePlan ? 'disabled' : '' ?>>
                        <span><?= e('common.select_all') ?></span>
                    </label>
                    <span class="bulk-selected-count" id="bulk-selected-count"><?= e('common.selected_count', ['count' => fmt_number(0)]) ?></span>
                    <?php if ($isFreePlan): ?>
                        <button type="button" class="bulk-download-btn locked" disabled aria-label="<?= e('bulk.aria_upgrade_download') ?>">
                            <span class="lock-icon">&#128274;</span> <?= e('common.download_zip') ?>
                        </button>
                        <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn"><?= e('common.upgrade_to_unlock') ?></a>
                    <?php else: ?>
                        <button type="submit" class="bulk-download-btn" id="bulk-download-btn" disabled>
                            <?= e('common.download_zip') ?>
                        </button>
                    <?php endif; ?>
                </div>

                <table id="bulkInvoicesTable" class="display">
                    <thead>
                        <tr>
                            <th style="width: 40px;">&nbsp;</th>
                            <th><?= e('table.order_id') ?></th>
                            <th><?= e('table.date') ?></th>
                            <th><?= e('table.customer') ?></th>
                            <th><?= e('table.amount') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generated as $row): ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="order_ids[]"
                                        value="<?= htmlspecialchars($row['order_id']) ?>"
                                        class="bulk-row-check"
                                        <?= $isFreePlan ? 'disabled' : '' ?>
                                    >
                                </td>
                                <td><?= htmlspecialchars($row['order_name'] ?? ('#' . $row['order_number'])) ?></td>
                                <td data-order="<?= htmlspecialchars($row['created_at']) ?>"><?= htmlspecialchars(fmt_date($row['created_at'])) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars(fmt_currency($row['total_price'], $row['currency'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php endif; ?>
    </div>
</main>

<!-- Message Box -->
<div id="message-box" style="display:none; position:fixed; top:20px; right:20px; padding:10px 20px; border-radius:5px; color:#fff; z-index:9999; font-weight:bold;"></div>

<?php include 'footer.php'; ?>
