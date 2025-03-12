<?php
// Verify HMAC
function verifyHmac($params, $secret) {
    if (empty($params['hmac'])) return false;
    $hmac = $params['hmac'];
    unset($params['hmac']);
    ksort($params);
    $query = http_build_query($params);
    return hash_equals($hmac, hash_hmac('sha256', $query, $secret));
}
// Verify shop domain format
function validateShopDomain($shop) {
    return preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop);
}

// Generate installation redirect URL
function getInstallUrl($shop, $scopes, $redirectUrl) {
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['nonce'] = $nonce;
    
    return "https://{$shop}/admin/oauth/authorize?" . http_build_query([
        'client_id' => SHOPIFY_API_KEY,
        'scope' => $scopes,
        'redirect_uri' => $redirectUrl,
        'state' => $nonce
    ]);
}