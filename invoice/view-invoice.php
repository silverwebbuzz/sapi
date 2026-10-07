<?php
/**
 * Returns one already-generated invoice PDF as base64 JSON for the
 * View Invoice modal.
 *
 * The listing pages no longer load pdf_invoice for every row (that exhausted
 * PHP's memory limit on stores with many invoices), so the PDF is fetched
 * here only when the merchant opens it. Same JSON + data: URL pattern as the
 * packing-slip viewer, which avoids the frame-ancestors CSP inside Shopify.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'i18n.php';

i18n_boot();

header('Content-Type: application/json');

$shop_id  = isset($_GET['shop_id'])  ? (int)$_GET['shop_id'] : 0;
$order_id = isset($_GET['order_id']) ? (string)$_GET['order_id'] : '';

if ($shop_id <= 0 || $order_id === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => t('errors.missing_shop_or_order')]);
    exit;
}

$store = DBHelper::selectOne(
    "SELECT * FROM stores WHERE id = ? AND status = 'installed'",
    "i",
    [$shop_id]
);
if (!$store) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => t('errors.store_not_found')]);
    exit;
}
i18n_boot($store);

$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));
$row = DBHelper::selectOne(
    "SELECT pdf_invoice FROM `$invoice_table` WHERE order_id = ?",
    "s",
    [$order_id]
);
if (!$row || empty($row['pdf_invoice'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => t('errors.invoice_not_found')]);
    exit;
}

echo json_encode([
    'status'     => 'success',
    'pdf_base64' => $row['pdf_invoice'],
]);
exit;
