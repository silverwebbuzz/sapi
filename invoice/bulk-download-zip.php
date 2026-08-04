<?php
/**
 * Bulk download endpoint: builds a ZIP of selected PDFs and streams it.
 *
 * Accepts two document types via the `type` POST field:
 *   - invoice       (default) — zips pdf_invoice column
 *   - packing_slip            — zips packing_slip_pdf column
 *
 * Gates on paid plan server-side so the disabled-button UI can't be bypassed.
 */
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'i18n.php';

i18n_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(t('errors.method_not_allowed'));
}

$shop_id   = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : 0;
$order_ids = isset($_POST['order_ids']) && is_array($_POST['order_ids']) ? $_POST['order_ids'] : [];
$type      = isset($_POST['type']) ? (string)$_POST['type'] : 'invoice';

// Everything this endpoint emits is merchant-facing, so switch to the
// merchant's language before building any message — including the document
// nouns in $typeMap below.
if ($shop_id > 0) {
    i18n_boot(DBHelper::selectOne("SELECT * FROM stores WHERE id = ?", "i", [$shop_id]));
}

// Whitelist the type so users can't inject an arbitrary column name.
$typeMap = [
    'invoice' => [
        'column'        => 'pdf_invoice',
        'statusColumn'  => 'invoice_status',
        'filePrefix'    => 'invoice',
        'zipPrefix'     => 'invoices',
        // Used inside translated sentences below, so it is a key not a literal.
        'humanName'     => t('bulk.noun_invoice_plural'),
    ],
    'packing_slip' => [
        'column'        => 'packing_slip_pdf',
        'statusColumn'  => 'packing_slip_status',
        'filePrefix'    => 'packing-slip',
        'zipPrefix'     => 'packing-slips',
        'humanName'     => t('bulk.noun_packing_slip_plural'),
    ],
];
if (!isset($typeMap[$type])) {
    http_response_code(400);
    die(t('errors.unknown_type'));
}
$cfg = $typeMap[$type];

if ($shop_id <= 0 || empty($order_ids)) {
    http_response_code(400);
    die(t('errors.missing_shop_or_selection'));
}

if (count($order_ids) > 500) {
    http_response_code(400);
    die(t('errors.too_many_selected', ['items' => $cfg['humanName']]));
}

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

$isFreePlan = ((float)($store['price'] ?? 0) == 0.00);
if ($isFreePlan) {
    http_response_code(403);
    die(t('errors.bulk_paid_only'));
}

$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));

// For packing slips, only proceed if the migration ran for this store.
if ($type === 'packing_slip') {
    $colsRes = DBHelper::select("SHOW COLUMNS FROM `$invoice_table` LIKE 'packing_slip_pdf'", "", []);
    if (empty($colsRes)) {
        http_response_code(409);
        die(t('errors.packing_slip_storage_missing'));
    }
}

$placeholders = implode(',', array_fill(0, count($order_ids), '?'));
$types  = str_repeat('s', count($order_ids));
$params = array_map('strval', $order_ids);

$col       = $cfg['column'];          // safe — comes from whitelisted map
$statusCol = $cfg['statusColumn'];    // ditto

$rows = DBHelper::select(
    "SELECT order_id, order_name, order_number, `$col` AS pdf_blob
       FROM `$invoice_table`
      WHERE order_id IN ($placeholders)
        AND `$statusCol` = 'generated'
        AND `$col` IS NOT NULL
        AND `$col` != ''",
    $types,
    $params
);

if (empty($rows)) {
    http_response_code(404);
    die(t('errors.none_generated', ['items' => $cfg['humanName']]));
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die(t('errors.zip_missing'));
}

$tmpZip = tempnam(sys_get_temp_dir(), 'invzip_');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpZip);
    http_response_code(500);
    die(t('errors.zip_open_failed'));
}

$added = 0;
foreach ($rows as $r) {
    $pdfBytes = base64_decode($r['pdf_blob'], true);
    if ($pdfBytes === false || $pdfBytes === '') {
        continue;
    }
    $label = $r['order_name'] ?: ($r['order_number'] ?: $r['order_id']);
    $safeLabel = preg_replace('/[^A-Za-z0-9_\-#]/', '_', $label);
    $filename = $cfg['filePrefix'] . '_' . $safeLabel . '.pdf';
    $zip->addFromString($filename, $pdfBytes);
    $added++;
}
$zip->close();

if ($added === 0) {
    @unlink($tmpZip);
    http_response_code(500);
    die(t('errors.none_decodable', ['items' => $cfg['humanName']]));
}

$downloadName = $cfg['zipPrefix'] . '_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store');

readfile($tmpZip);
@unlink($tmpZip);
exit;
