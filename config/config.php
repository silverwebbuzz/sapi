<?php
define('BASE_PATH', '/home/u843459215/domains/silverwebbuzz.com/public_html/sapi');
define('BASE_URL', 'https://sapi.silverwebbuzz.com');
define('BASE_TEMPLATE_URL', 'https://sapi.silverwebbuzz.com/template/');
define('BASE_SHOPIFY_AF_URL', 'https://sapi.silverwebbuzz.com/invoice/');
define('BASE_OWNER_STORE_LOGO_URL', 'https://sapi.silverwebbuzz.com/invoice/uploads/logos/');

define('PUBLIC_URL', BASE_URL);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'silverwebbuzz_in_sapi');
define('DB_USER', 'silverwebbuzz_in_sapi');
define('DB_PASS', 'Sapi@1109silver');

// Shopify Configuration
define('SHOPIFY_APP_NAME', 'SWB : Auto PDF Invoice');
define('SHOPIFY_APP_HANDLE', 'swb-auto-pdf-invoices');
define('SHOPIFY_APP_URL', BASE_URL);
define('LIVE_SHOPIFY_APP_URL', "https://apps.shopify.com/swb-auto-pdf-invoices");
define('SHOPIFY_API_KEY', '376ce2c1e19ffa08ea8ad26e038ff4f5');
define('SHOPIFY_API_SECRET', 'af6436e75bd208e3ce4dc8c153d5ad6f');
define('SHOPIFY_API_VERSION', '2025-01');
define('SHOPIFY_APP_SCOPES', 'read_products,read_orders,read_customers,read_assigned_fulfillment_orders,read_merchant_managed_fulfillment_orders,read_third_party_fulfillment_orders,read_themes');
define('SHOPIFY_APP_REDIRECT', true);
define('ADMIN_URL_FORMAT', 'https://admin.shopify.com/store/%s/apps/%s');

define('COOKIE_KEY', 'abcdefghijklmnopqrstuvwxyz123456'); // 32-character random key

define('DEFAULT_EMAIL_SUBJECT', "Invoice #{invoice_number} from {shop_name}");
/*$default_body = '
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .header { color: #333366; }
        .footer { margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <h2 class="header">Dear {customer_name},</h2>
    <p>Thank you for your order! Please find your invoice #{invoice_number} attached.</p>
    <p><strong>Order Total:</strong> {currency} {total_price}</p>
    <p><strong>Date:</strong> {created_at}</p>
    <div class="footer">
        <p>If you have any questions, please contact our support team.</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
';*/

$default_body = '<html>
<head>
  <style>
    body {
      font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f9f9f9;
      padding: 20px;
      color: #333;
    }
    .container {
      max-width: 600px;
      margin: auto;
      background-color: #ffffff;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .header {
      color: #2c3e50;
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    .invoice-info {
      margin: 20px 0;
    }
    .footer {
      font-size: 12px;
      color: #777;
      margin-top: 30px;
      border-top: 1px solid #eee;
      padding-top: 15px;
    }
    .btn {
      display: inline-block;
      padding: 10px 20px;
      margin-top: 20px;
      background-color: #3b82f6;
      color: #fff;
      text-decoration: none;
      border-radius: 5px;
    }
    .btn:hover {
      background-color: #2563eb;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2 class="header">Hi {customer_name},</h2>
    <p>Thank you for shopping with us! We’re excited to share your invoice for your recent order.</p>

    <div class="invoice-info">
      <p><strong>Invoice Number:</strong> #{invoice_number}</p>
      <p><strong>Order Total:</strong> {currency} {total_price}</p>
      <p><strong>Date:</strong> {created_at}</p>
    </div>

    <div class="footer">
      <p>If you have any questions or need assistance, feel free to reach out to our support team anytime.</p>
      <p>Thank you again for your business!</p>
      <p><em>This is a computer-generated invoice and does not require a signature.</em></p>
    </div>
  </div>
</body>
</html>';
define('DEFAULT_EMAIL_BODY', $default_body);

session_start();