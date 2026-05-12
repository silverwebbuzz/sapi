<?php
/**
 * Bulk download endpoint: builds a ZIP of selected invoice PDFs and streams
 * it to the browser. Gates on paid plan server-side so the disabled-button
 * UI can't be bypassed by crafting a direct POST.
 */
require_once '../config/config.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

$shop_id   = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : 0;
$order_ids = isset($_POST['order_ids']) && is_array($_POST['order_ids']) ? $_POST['order_ids'] : [];

if ($shop_id <= 0 || empty($order_ids)) {
    http_response_code(400);
    die('Missing shop_id or order selection.');
}

// Cap to a sane upper bound so a malicious POST can't OOM the server.
if (count($order_ids) > 500) {
    http_response_code(400);
    die('Too many invoices selected. Please pick 500 or fewer at a time.');
}

// Look up the store + active plan together.
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
    die('Store not found.');
}

// Server-side paid-plan gate. UI is also disabled for free users, but never
// trust the client.
$isFreePlan = ((float)($store['price'] ?? 0) == 0.00);
if ($isFreePlan) {
    http_response_code(403);
    die('Bulk download requires a paid plan.');
}

// Sanitized table name — same scheme used everywhere else in the app.
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));

// Build a parametrised IN (?, ?, ...) so a crafted POST can't inject SQL.
$placeholders = implode(',', array_fill(0, count($order_ids), '?'));
$types  = str_repeat('s', count($order_ids));
$params = array_map('strval', $order_ids);

$invoices = DBHelper::select(
    "SELECT order_id, order_name, order_number, pdf_invoice
       FROM `$invoice_table`
      WHERE order_id IN ($placeholders)
        AND invoice_status = 'generated'
        AND pdf_invoice IS NOT NULL
        AND pdf_invoice != ''",
    $types,
    $params
);

if (empty($invoices)) {
    http_response_code(404);
    die('None of the selected invoices have a generated PDF.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('Server is missing the ZipArchive extension. Please contact support.');
}

// Build the zip in a temp file (memory-safe even for hundreds of PDFs).
$tmpZip = tempnam(sys_get_temp_dir(), 'invzip_');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpZip);
    http_response_code(500);
    die('Failed to open temporary zip file.');
}

$added = 0;
foreach ($invoices as $inv) {
    $pdfBytes = base64_decode($inv['pdf_invoice'], true);
    if ($pdfBytes === false || $pdfBytes === '') {
        continue;
    }
    // Filename inside the zip: prefer order_name (#3112), fall back to order_number / id.
    $label = $inv['order_name'] ?: ($inv['order_number'] ?: $inv['order_id']);
    $safeLabel = preg_replace('/[^A-Za-z0-9_\-#]/', '_', $label);
    $filename = "invoice_{$safeLabel}.pdf";
    $zip->addFromString($filename, $pdfBytes);
    $added++;
}
$zip->close();

if ($added === 0) {
    @unlink($tmpZip);
    http_response_code(500);
    die('Could not decode any of the selected invoice PDFs.');
}

// Stream the zip back to the browser, then clean up.
$downloadName = 'invoices_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store');

readfile($tmpZip);
@unlink($tmpZip);
exit;
