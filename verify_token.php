<?php 
require_once 'config/config.php';
require_once 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

//$headers = getallheaders();
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

// Replace with your Shopify App Secret from Partner Dashboard
$appSecret = SHOPIFY_API_SECRET;

try {
    if (!$token) {
        throw new Exception("Missing token");
    }

    $decoded = JWT::decode($token, new Key($appSecret, 'HS256'));
    $shop = parse_url($decoded->dest, PHP_URL_HOST);
    echo json_encode([
        'status' => 'success',
        'message' => 'Session token is valid.',
        'shop' => $shop ?? null,
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid session token: ' . $e->getMessage()
    ]);
}