<?php 
require_once 'config/config.php';
require_once 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

/**
 * Read the Authorization header in a way that survives every Apache/cPanel
 * quirk: cGI/FastCGI commonly strips it, mod_rewrite stashes it as
 * REDIRECT_HTTP_AUTHORIZATION, and case-folding varies by SAPI.
 */
function read_authorization_header(): string {
    // 1) Stock CGI variable
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }
    // 2) After mod_rewrite forwards it via [E=HTTP_AUTHORIZATION:%1]
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // 3) Apache module
    if (function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        foreach ($h as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                return $value;
            }
        }
    }
    // 4) getallheaders fallback (already case-insensitive in PHP 8+)
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        foreach ($h as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                return $value;
            }
        }
    }
    return '';
}

$authHeader = read_authorization_header();

$token = str_replace('Bearer ', '', $authHeader);

// Replace with your Shopify App Secret from Partner Dashboard
$appSecret = SHOPIFY_API_SECRET;

try {
    if (!$token) {
        throw new Exception("Missing token");
    }
    JWT::$leeway = 60;
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