<?php
define('BASE_PATH', '/home/u843459215/domains/silverwebbuzz.com/public_html/sapi');
define('BASE_URL', 'https://sapi.silverwebbuzz.com');
define('BASE_TEMPLATE_URL', 'https://sapi.silverwebbuzz.com/template/');
define('BASE_SHOPIFY_AF_URL', 'https://sapi.silverwebbuzz.com/shopifyclient/');


define('PUBLIC_URL', BASE_URL);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u843459215_sapi');
define('DB_USER', 'u843459215_sapi');
define('DB_PASS', 'Sapi@1109');

// Shopify Configuration
define('SHOPIFY_APP_NAME', 'SWB : Auto PDF Invoice');
define('SHOPIFY_APP_HANDLE', 'swb-auto-pdf-invoices');
define('SHOPIFY_APP_URL', BASE_URL);
define('SHOPIFY_API_KEY', '376ce2c1e19ffa08ea8ad26e038ff4f5');
define('SHOPIFY_API_SECRET', 'af6436e75bd208e3ce4dc8c153d5ad6f');
define('SHOPIFY_API_VERSION', '2025-01');
define('SHOPIFY_APP_SCOPES', 'read_products,read_orders,read_customers,read_assigned_fulfillment_orders,read_merchant_managed_fulfillment_orders,read_third_party_fulfillment_orders');

session_start();