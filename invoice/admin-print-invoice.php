<?php
require_once '../vendor/autoload.php';
require_once '../config/db.php';
require_once 'shopify_functions.php';
require_once 'helper.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Access-Control-Allow-Origin: https://admin.shopify.com');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Vary: Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function getAuthorizationHeader() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                return trim($value);
            }
        }
    }
    return null;
}

function getBearerToken() {
    $header = getAuthorizationHeader();
    if (!$header) {
        return null;
    }
    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function decodeShopifySessionToken($token) {
    try {
        JWT::$leeway = 60;
        $decoded = JWT::decode($token, new Key(SHOPIFY_API_SECRET, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        error_log('Session token decode failed: ' . $e->getMessage());
        return null;
    }
}

function respondJson($statusCode, $message) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

$order_id = $_GET['order_id'] ?? '';
$shop = $_GET['shop'] ?? '';

if (!$order_id) {
    respondJson(400, 'Missing order_id.');
}

$token = getBearerToken();
if ($token) {
    $decoded = decodeShopifySessionToken($token);
    if ($decoded && isset($decoded->dest)) {
        $shop = parse_url($decoded->dest, PHP_URL_HOST);
    }
}

if (!$shop || !validateShopDomain($shop)) {
    respondJson(401, 'Invalid or missing shop domain.');
}

$store = DBHelper::selectOne(
    "SELECT id FROM stores WHERE `shop` = ? AND `status` = ? LIMIT 1",
    "ss",
    [$shop, 'installed']
);

if (!$store) {
    respondJson(404, 'Shop not found or not installed.');
}

$pdfBytes = getInvoicePdfContent($store['id'], $order_id);
if (!$pdfBytes) {
    respondJson(404, 'Invoice PDF not found for the requested order.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="SWB-Auto-PDF-Invoice-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $order_id) . '.pdf"');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $pdfBytes;
exit;
