<?php require_once '../config/config.php';
require_once '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

// Replace with your Shopify App Secret from Partner Dashboard
$appSecret = 'SHOPIFY_API_SECRET';

try {
    $decoded = JWT::decode($token, new Key($appSecret, 'HS256'));

    // You can now use $decoded->dest or $decoded->sub
    echo json_encode([
        'status' => 'success',
        'message' => 'Session token is valid.',
        'shop' => $decoded->dest ?? null,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid session token: ' . $e->getMessage()
    ]);
}