<?php
/**
 * Packing slip generator endpoint.
 *
 * - Server-side paid-plan gate (UI also locks the button but never trust client).
 * - Supports GET (?shop_id=&order_id=) for single calls AND the same params
 *   POSTed during the bulk batch from the orders/packing-slip page.
 * - Inline preview when ?view=1 is passed (used by the View Packing Slip button).
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'i18n.php';
require_once '../vendor/autoload.php';

i18n_boot();

$shop_id  = isset($_REQUEST['shop_id'])  ? (int)$_REQUEST['shop_id'] : 0;
$order_id = isset($_REQUEST['order_id']) ? (string)$_REQUEST['order_id'] : '';
$view     = !empty($_REQUEST['view']);

if ($shop_id <= 0 || $order_id === '') {
    http_response_code(400);
    die(t('errors.missing_shop_or_order'));
}

// Confirm the store is installed and look up its active plan price.
$store = DBHelper::selectOne(
    "SELECT s.id, s.shop, ss.price
       FROM stores s
       LEFT JOIN store_subscriptions ss
         ON ss.store_id = s.id AND ss.status = 'active'
      WHERE s.id = ? AND s.status = 'installed'
      ORDER BY ss.activated_on DESC
      LIMIT 1",
    "i",
    [$shop_id]
);
if (!$store) {
    http_response_code(404);
    die(t('errors.store_not_found'));
}

// Errors from here on are shown to the merchant, so use their language.
$store_row = DBHelper::selectOne("SELECT * FROM stores WHERE id = ?", "i", [$shop_id]);
i18n_boot($store_row);

$isFreePlan = ((float)($store['price'] ?? 0) == 0.00);

// View-mode (just renders an already-generated PDF inline) is OK for free
// users too — they may have packing slips generated before downgrading.
if (!$view && $isFreePlan) {
    http_response_code(403);
    die(t('errors.packing_slip_paid_only'));
}

if ($view) {
    $invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));
    $row = DBHelper::selectOne(
        "SELECT packing_slip_pdf FROM `$invoice_table` WHERE order_id = ?",
        "s",
        [$order_id]
    );
    if (!$row || empty($row['packing_slip_pdf'])) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => t('errors.packing_slip_not_generated')]);
        exit;
    }
    // Return the base64 PDF as JSON so the page can render it via a
    // data: URL inside the modal. We avoid serving the embed inline
    // because the app's CSP (frame-ancestors) blocks same-domain HTTP
    // framing inside the Shopify iframe.
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'pdf_base64' => $row['packing_slip_pdf'],
    ]);
    exit;
}

$result = generatePackingSlip($shop_id, $order_id);

// Bulk loop on the client expects a simple JSON response.
header('Content-Type: application/json');
if (($result['status'] ?? '') !== 'success') {
    http_response_code(500);
}
echo json_encode($result);
