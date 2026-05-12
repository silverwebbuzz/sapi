<?php
include 'header.php';
include 'nav.php';

// Free plans see the list but cannot trigger the download.
$isFreePlan = ((float)($currentPlan['price'] ?? 0) == 0.00);

// Only list invoices that already have a generated PDF — bulk download
// just zips what exists, never (re)generates on the fly.
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
$generated_invoices = DBHelper::select(
    "SELECT order_id, order_number, order_name, customer_name, currency, total_price, created_at
       FROM `$invoice_table`
      WHERE invoice_status = 'generated'
        AND pdf_invoice IS NOT NULL
        AND pdf_invoice != ''
      ORDER BY created_at DESC",
    "",
    []
);
?>

<main class="main-content">
    <div class="page-header">
        <h2>Bulk Download Invoices</h2>
        <h3>Select multiple generated invoices and download them as a single ZIP file.</h3>
    </div>

    <?php if ($isFreePlan): ?>
        <div class="bulk-upgrade-banner">
            <div>
                <strong>Bulk download is a paid feature.</strong>
                <p>You can preview the list of generated invoices below, but downloading multiple invoices at once requires a paid plan.</p>
            </div>
            <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>" class="upgrade-btn">Upgrade Plan</a>
        </div>
    <?php endif; ?>

    <div class="orders-card">
        <?php if (empty($generated_invoices)): ?>
            <p style="padding: 24px; text-align: center; color: #6b7280; font-size: 14px;">
                No generated invoices yet. Generate invoices from the
                <a href="index?shop=<?= htmlspecialchars($shop) ?>" style="color: #111827; text-decoration: underline;">Dashboard</a>
                or <a href="order?shop=<?= htmlspecialchars($shop) ?>" style="color: #111827; text-decoration: underline;">Shopify Orders</a>
                page first, then come back here to bulk download them.
            </p>
        <?php else: ?>
            <form id="bulk-download-form" method="post" action="bulk-download-zip" target="_blank">
                <input type="hidden" name="shop_id" value="<?= htmlspecialchars($shop_id) ?>">

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
                        <?php foreach ($generated_invoices as $inv): ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="order_ids[]"
                                        value="<?= htmlspecialchars($inv['order_id']) ?>"
                                        class="bulk-row-check"
                                        <?= $isFreePlan ? 'disabled' : '' ?>
                                    >
                                </td>
                                <td><?= htmlspecialchars($inv['order_name'] ?? ('#' . $inv['order_number'])) ?></td>
                                <td><?= htmlspecialchars($inv['created_at']) ?></td>
                                <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                                <td><?= htmlspecialchars($inv['currency']) ?> <?= number_format((float)$inv['total_price'], 2) ?></td>
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
