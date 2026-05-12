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
    $heading      = 'Bulk Download Packing Slips';
    $subheading   = 'Select multiple generated packing slips and download them as a single ZIP file.';
    $generateHref = 'packing-slip?shop=' . htmlspecialchars($shop);
    $generateLabel = 'Packing Slips page';
    $emptyMessage = 'No generated packing slips yet. Generate them from the';
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
    $heading      = 'Bulk Download Invoices';
    $subheading   = 'Select multiple generated invoices and download them as a single ZIP file.';
    $generateHref = 'index?shop=' . htmlspecialchars($shop);
    $generateLabel = 'Dashboard';
    $emptyMessage = 'No generated invoices yet. Generate invoices from the';
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
           class="<?= $type === 'invoice' ? 'active' : '' ?>">Invoices</a>
        <a href="bulk-download?shop=<?= htmlspecialchars($shop) ?>&type=packing_slip"
           class="<?= $type === 'packing_slip' ? 'active' : '' ?>">Packing Slips</a>
    </div>

    <?php if ($type === 'packing_slip' && !$packingSlipReady): ?>
        <div class="bulk-upgrade-banner" style="background:#fef2f2; border-color:#fca5a5;">
            <div>
                <strong style="color:#991b1b;">Packing slips not enabled yet.</strong>
                <p style="color:#7f1d1d;">A one-time database update is required to enable packing slips for your store. Please contact support.</p>
            </div>
        </div>
    <?php elseif ($isFreePlan): ?>
        <div class="bulk-upgrade-banner">
            <div>
                <strong>Bulk download is a paid feature.</strong>
                <p>You can preview the list below, but downloading multiple files at once requires a paid plan.</p>
            </div>
            <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn">Upgrade Plan</a>
        </div>
    <?php endif; ?>

    <div class="orders-card">
        <?php if (empty($generated)): ?>
            <p style="padding: 24px; text-align: center; color: #6b7280; font-size: 14px;">
                <?= htmlspecialchars($emptyMessage) ?>
                <a href="<?= $generateHref ?>" style="color: #111827; text-decoration: underline;"><?= htmlspecialchars($generateLabel) ?></a>
                first, then come back here to bulk download.
            </p>
        <?php else: ?>
            <form id="bulk-download-form" method="post" action="bulk-download-zip" target="_blank">
                <input type="hidden" name="shop_id" value="<?= htmlspecialchars($shop_id) ?>">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

                <div class="bulk-toolbar">
                    <label class="bulk-select-all">
                        <input type="checkbox" id="bulk-select-all" <?= $isFreePlan ? 'disabled' : '' ?>>
                        <span>Select all</span>
                    </label>
                    <span class="bulk-selected-count" id="bulk-selected-count">0 selected</span>
                    <?php if ($isFreePlan): ?>
                        <button type="button" class="bulk-download-btn locked" disabled aria-label="Upgrade to unlock bulk download">
                            <span class="lock-icon">&#128274;</span> Download ZIP
                        </button>
                        <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn">Upgrade to unlock</a>
                    <?php else: ?>
                        <button type="submit" class="bulk-download-btn" id="bulk-download-btn" disabled>
                            Download ZIP
                        </button>
                    <?php endif; ?>
                </div>

                <table id="bulkInvoicesTable" class="display">
                    <thead>
                        <tr>
                            <th style="width: 40px;">&nbsp;</th>
                            <th>ORDER ID</th>
                            <th>DATE</th>
                            <th>CUSTOMER</th>
                            <th>AMOUNT</th>
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
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['currency']) ?> <?= number_format((float)$row['total_price'], 2) ?></td>
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
