<?php
function generatepdf($shop_id,$order_id){
    // Fetch the correct invoice table for this shop
    $table_query = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $table_query->bind_param("s", $shop_id);
    $table_query->execute();
    $result = $table_query->get_result();

    if ($result->num_rows > 0) {
        $shop_data = $result->fetch_assoc();

        $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
        $invoice_table = "invoices_" . $shop_name;

        
        // Fetch invoice details
        $stmt = $conn->prepare("SELECT * FROM `$invoice_table` WHERE order_id = ?");
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $invoice_result = $stmt->get_result();

        if ($invoice_result->num_rows > 0) {
            $invoice = $invoice_result->fetch_assoc();

            // Decode JSON data
            $billing_address = json_decode($invoice['billing_address'], true);
            $shipping_address = json_decode($invoice['shipping_address'], true);
            $products = json_decode($invoice['products'], true);

            // Prepare order items HTML
            $items_html = '';
            $counter = 1;
            foreach ($products as $item) {
                $tax_rate = 0;
                $tax_amount = 0;
                
                // Get tax information if available
                if (isset($item['tax_lines']) && !empty($item['tax_lines'])) {
                    $tax_rate = $item['tax_lines'][0]['rate'] * 100;
                    $tax_amount = $item['tax_lines'][0]['price'];
                }
                
                $items_html .= '<tr>';
                $items_html .= '<td>'.$item['name'].'</td>';
                $items_html .= '<td>'.$item['quantity'].'</td>';
                $items_html .= '<td class="text-right">'.$invoice['currency'].' '.number_format($item['price'], 2).'</td>';
                //$items_html .= '<td class="text-right">'.$tax_rate.'%</td>';
                //$items_html .= '<td class="text-right">'.$invoice['currency'].' '.number_format($tax_amount, 2).'</td>';
                $items_html .= '<td class="text-right">'.$invoice['currency'].' '.number_format($item['price'] * $item['quantity'], 2).'</td>';
                $items_html .= '</tr>';
                $counter++;
            }

            // Prepare replacements array
            $replacements = [
                '{{ Company_Logo }}' => $shop_data['logo_url'] ? '<img src="'.$shop_data['logo_url'].'" class="logo">' : '',
                '{{ Company_Name }}' => $shop_data['store_name'],
                '{{ Company_Address }}' => $shop_data['address1']."<br/>".$shop_data['address2']."<br/>".$shop_data['city']." ".$shop_data['province']." ".$shop_data['province_code']." ".$shop_data['zip']."<br/>".$shop_data['country_name'] ,
                '{{ Company_Phone }}' => $shop_data['phone'],
                '{{ Company_Email }}' => $shop_data['email'],
                '{{ Company_GSTIN }}' => $shop_data['gstin'] ?? '',
                '{{ Order_Number }}' => $invoice['order_name'],
                '{{ Invoice_Date }}' => date('d/m/Y', strtotime($invoice['created_at'])),
                '{{ Due_Date }}' => date('d/m/Y', strtotime($invoice['created_at'].' +15 days')),
                '{{ Billing_Name }}' => $billing_address['name'] ?? '',
                '{{ Billing_Address1 }}' => $billing_address['address1'] ?? '',
                '{{ Billing_Address2 }}' => $billing_address['address2'] ?? '',
                '{{ Billing_City }}' => $billing_address['city'] ?? '',
                '{{ Billing_State }}' => $billing_address['province'] ?? '',
                '{{ Billing_Zip }}' => $billing_address['zip'] ?? '',
                '{{ Billing_Country }}' => $billing_address['country'] ?? '',
                '{{ Billing_GSTIN }}' => '', // Add GSTIN if available
                '{{ Billing_Email }}' => $invoice['customer_email'] ?? '',
                '{{ Billing_Phone }}' => $billing_address['phone'] ?? 'No phone number',
                '{{ Shipping_Name }}' => isset($shipping_address['name']) ? $shipping_address['name'] : ($billing_address['name'] ?? ''),
                '{{ Shipping_Address1 }}' => isset($shipping_address['address1']) ? $shipping_address['address1'] : ($billing_address['address1'] ?? ''),
                '{{ Shipping_Address2 }}' => isset($shipping_address['address2']) ? $shipping_address['address2'] : ($billing_address['address2'] ?? ''),
                '{{ Shipping_City }}' => isset($shipping_address['city']) ? $shipping_address['city'] : ($billing_address['city'] ?? ''),
                '{{ Shipping_State }}' => isset($shipping_address['province']) ? $shipping_address['province'] : ($billing_address['province'] ?? ''),
                '{{ Shipping_Zip }}' => isset($shipping_address['zip']) ? $shipping_address['zip'] : ($billing_address['zip'] ?? ''),
                '{{ Shipping_Country }}' => isset($shipping_address['country']) ? $shipping_address['country'] : ($billing_address['country'] ?? ''),
                '{{ Order_Items }}' => $items_html,
                '{{ Subtotal }}' => $invoice['currency'].' '.number_format($invoice['subtotal_price'], 2),
                '{{ Tax_Amount }}' => $invoice['currency'].' '.number_format($invoice['tax_amount'], 2),
                '{{ Shipping_Cost }}' => $invoice['currency'].' '.number_format($invoice['shipping_cost'], 2),
                '{{ Discount_Amount }}' => $invoice['currency'].' '.number_format($invoice['discount_amount'], 2),
                '{{ Total_Amount }}' => $invoice['currency'].' '.number_format($invoice['total_price'], 2),
                '{{ Payment_Method }}' => $invoice['payment_method'] ?? 'Unknown',
                '{{ Payment_Status }}' => ucfirst($invoice['order_status'])
            ];

            // Load HTML template
            $template_id = $shop_data['invoice_templates_id'];
            // Fetch template details
            $stmt_temp = $conn->prepare("SELECT * FROM `invoice_templates` WHERE id = ?");
            $stmt_temp->bind_param("s", $template_id);
            $stmt_temp->execute();
            $template_result = $stmt_temp->get_result();
            $template_html = $template_result->fetch_assoc();
            $template = file_get_contents('../invoice/temp/'.$template_html['template_file']);
            $html = str_replace(array_keys($replacements), array_values($replacements), $template);

            // Create new PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor($shop_data['store_name']);
            $pdf->SetTitle('Invoice '.$invoice['order_name']);
            $pdf->SetSubject('Invoice');

            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Add a page
            $pdf->AddPage();

            // Output HTML content
            $pdf->writeHTML($html, true, false, true, false, '');

            // Close and output PDF document
            //$pdf_content = $pdf->Output('invoice_'.$invoice['order_name'].'.pdf', 'I');
            $pdf_content = $pdf->Output('', 'S');
            $encoded_pdf = base64_encode($pdf_content); // Encode PDF for storage


            // Single database update for both PDF and email status
            $update_stmt = $conn->prepare("UPDATE `$invoice_table` SET  invoice_status = 'generated', pdf_invoice = ? WHERE order_id = ? ");
            $update_stmt->bind_param("ss", $encoded_pdf, $order_id);
            $update_stmt->execute();

            $up_sub_stmt = $conn->prepare("UPDATE `store_subscriptions` SET  order_used = order_used+1  WHERE store_id = ? ");
            $up_sub_stmt->bind_param("s", $shop_id );
            $up_sub_stmt->execute();

            //header("location:javascript://history.go(-1)");
        } else {
            return ("No invoice found with the specified order ID.");
        }
    } else {
        return ("No shop found with the specified ID.");
    }
}

function sendemail($shop_id,$order_id){
    // Fetch the correct invoice table for this shop
    $table_query = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $table_query->bind_param("s", $shop_id);
    $table_query->execute();
    $result = $table_query->get_result();

    if ($result->num_rows > 0) {
        $shop_data = $result->fetch_assoc();

        $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
        $invoice_table = "invoices_" . $shop_name;

        
        // Fetch invoice details
        $stmt = $conn->prepare("SELECT * FROM `$invoice_table` WHERE order_id = ?");
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $invoice_result = $stmt->get_result();

        if ($invoice_result->num_rows > 0) {
            $invoice = $invoice_result->fetch_assoc();
            
            $decoded_pdf = base64_decode($invoice['pdf_invoice']);
            $billing_address = json_decode($invoice['billing_address'], true);

            $smtp_settings = json_decode($shop_data['smtp_settings'], true);

            $to_email = $invoice['customer_email'];
            $to_name = $invoice['customer_name'];
            $subject = str_replace('{invoice_number}',$invoice['order_name'],$smtp_settings['subject']);
            $body = $smtp_settings['body'];
            //When sending an email, you would replace the variables like this:
            $email_body = str_replace(
                ['{invoice_number}', '{customer_name}', '{total_price}', '{currency}', '{created_at}'],
                [$invoice['order_name'], $invoice['customer_name'], $invoice['total_price'], $invoice['currency'], $invoice['created_at']],
                $body
            );

            // Send email with attachment
            $email_sent = sendEmailWithAttachment( $to_email,$to_name, $subject, $email_body, $decoded_pdf, "invoice_{$invoice['order_name']}.pdf");
            $email_status = $email_sent ? 'sent' : 'failed';

            // Single database update for both PDF and email status
            $update_stmt = $conn->prepare("UPDATE `$invoice_table` SET email_status = ? WHERE order_id = ? ");
            $update_stmt->bind_param("ss", $email_status, $order_id);
            $update_stmt->execute();

            $up_sub_stmt = $conn->prepare("UPDATE `store_subscriptions` SET  email_used = email_used+1  WHERE store_id = ? ");
            $up_sub_stmt->bind_param("s", $shop_id );
            $up_sub_stmt->execute();

            header("location:javascript://history.go(-1)");
        } else {
            die("No invoice found with the specified order ID.");
        }
    } else {
        die("No shop found with the specified ID.");
    }
}

// Email sending function
function sendEmailWithAttachment($to_email, $to_name, $subject, $html_body, $attachment_content, $attachment_name) {
   
    $mail = new PHPMailer(true);
 
     try {
         // Server settings
         //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
         $mail->isSMTP();
         $mail->Host       = 'mail.silverwebbuzz.com';
         $mail->SMTPAuth   = true;
         $mail->Username   = 'bhavik.koradiya@silverwebbuzz.com';
         $mail->Password   = 'Bhavik@1109';
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
         $mail->Port       = 587;
         $mail->SMTPKeepAlive = true;
 
         // Critical headers
         $mail->setFrom('bhavik.koradiya@silverwebbuzz.com', 'Bhavik Koradiya SWB', true);
         //$mail->addReplyTo('support@silverwebbuzz.com', 'Support Team');
         //$mail->addAddress('vishnu@silverwebbuzz.com', 'Vishnu Prajapati');
         $mail->addAddress($to_email, $to_name);
 
         // Content
         $mail->isHTML(true);
         $mail->Subject = $subject;
         $mail->Body = $html_body;
         $mail->AltBody = strip_tags($html_body);
         
         // Add PDF attachment from string
         $mail->addStringAttachment($attachment_content, $attachment_name, 'base64', 'application/pdf');
             
         // Delivery notifications
         //$mail->addCustomHeader('Return-Receipt-To: bhavik.koradiya@silverwebbuzz.com');
         //$mail->addCustomHeader('Disposition-Notification-To: bhavik.koradiya@silverwebbuzz.com');
         
         // Send with verification
         if (!$mail->send()) {
             throw new Exception('Send failed: ' . $mail->ErrorInfo);
         }
         return true;
         // Verify in mail logs
         echo "Message sent! Check:<br>";
         echo "1. Server mail logs<br>";
         echo "2. Spam folder<br>";
         echo "3. <a href='https://www.mail-tester.com' target='_blank'>Mail-Tester.com</a>";
 
     } catch (Exception $e) {
         echo "Error: " . $e->getMessage();
         error_log("Mail Error: " . $e->getMessage());
         return false;
         // Try fallback method
         //$headers = "From: noreply@silverwebbuzz.com\r\n";
         //$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
         //mail('recipient@example.com', 'Fallback Test', 'Test content', $headers);
         //echo "<br>Fallback method attempted";
         
     }
 }

/**
 * Encrypt cookie data (AES-256-CBC)
 */
function encryptCookie($data) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt(
        json_encode($data),
        'AES-256-CBC',
        COOKIE_KEY,
        0,
        $iv
    );
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt cookie data
 */
function decryptCookie($cookie) {
    $data = base64_decode($cookie);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $decrypted = openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
        COOKIE_KEY,
        0,
        $iv
    );
    return json_decode($decrypted, true);
}


/**
 * Handles direct access to the root URL
 */
function handleDirectAccess() {
    $shopifyLoginUrl = 'https://www.shopify.com/login';
    $shopParam = isset($_GET['shop']) ? htmlspecialchars($_GET['shop'], ENT_QUOTES, 'UTF-8') : SHOPIFY_APP_HANDLE;
    $installUrl = LIVE_SHOPIFY_APP_URL . '?shop=' . urlencode($shopParam);
    
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Install App</title>
</head>
<body>
    <h1>Please install this app via Shopify Admin</h1>
    <p>If you're a store owner, please <a href="$shopifyLoginUrl" target="_blank">login to Shopify</a> 
    and install this app from your admin dashboard.</p>
    <p>If you're being redirected from Shopify Admin, <a href="$installUrl">click here</a> to continue installation.</p>
</body>
</html>
HTML;
    exit;
}

/**
 * Handles OAuth request from Shopify Admin
 */
function handleOAuthRequest() {
    // Validate HMAC
    if (!validateHmac($_GET)) {
        die('Invalid HMAC');
    }

    $shop = $_GET['shop'];
    
    // Initial install - redirect to Shopify OAuth
    $install_url = getInstallUrl($shop, SHOPIFY_APP_SCOPES, SHOPIFY_APP_URL . '/shopify/callback');
    header("Location: $install_url");
    exit();

}
/**
 * Validates HMAC signature from Shopify
 */
function validateHmac($params) {
    $hmac = $params['hmac'];
    unset($params['hmac']);
    
    ksort($params);
    $computedHmac = hash_hmac('sha256', http_build_query($params), SHOPIFY_API_SECRET);
    
    return hash_equals($hmac, $computedHmac);
}

// Verify HMAC
function verifyHmac(array $params) {
    if (empty($params['hmac'])) {
        error_log("HMAC parameter missing");
        return false;
    }

    $hmac = $params['hmac'];
    unset($params['hmac']);
    
    // Debug: Log received parameters
    error_log("Received Params: " . print_r($params, true));
    
    ksort($params);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    
    // Debug: Log generated query string
    error_log("Generated Query: " . $query);
    
    $calculated_hmac = hash_hmac('sha256', $query, SHOPIFY_API_SECRET);
    
    // Debug: Log both HMACs
    error_log("Received HMAC: " . $hmac);
    error_log("Calculated HMAC: " . $calculated_hmac);
    
    return hash_equals($hmac, $calculated_hmac);
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

//Get Access Token from Shopify
function getAccessToken($shop,$code){
    $ch = curl_init("https://{$shop}/admin/oauth/access_token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'client_id' => SHOPIFY_API_KEY,
            'client_secret' => SHOPIFY_API_SECRET,
            'code' => $code
        ]
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $access_token = $response['access_token'];
}

//Get Shop all detials from shopify through rest API
function getShopDetailsRestAPI($shop,$access_token){
    $shopDetailsUrl = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/shop.json";
    $ch = curl_init($shopDetailsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: $access_token"
        ]
    ]);
    $shopDetailsResponse_json = curl_exec($ch);
    curl_close($ch);
    return $shopDetailsResponse_json;
}

function getShopLogo($shop, $access_token) {
    $url = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes.json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Shopify-Access-Token: ' . $access_token,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $themes = json_decode($response, true);
    
    // Get the active theme ID
    $active_theme_id = null;
    foreach ($themes['themes'] as $theme) {
        if ($theme['role'] === 'main') {
            $active_theme_id = $theme['id'];
            break;
        }
    }

    if (!$active_theme_id) {
        return null; // No active theme found
    }

    // Now fetch the theme assets (logo might be stored in settings_data.json)
    $url = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes/$active_theme_id/assets.json?asset[key]=config/settings_data.json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Shopify-Access-Token: ' . $access_token,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $assets = json_decode($response, true);
    
    if (isset($assets['asset']['value'])) {
        $settings_data = json_decode($assets['asset']['value'], true);
        if (isset($settings_data['current']['logo'])) {
            return $settings_data['current']['logo'];
        }
    }

    return $assets;
}

function registerShopifyWebhooks($shop, $access_token) {
    $webhook_url = SHOPIFY_APP_URL . '/shopify/webhook'; 
    $topics = [
        'orders/create',
        'orders/paid',
        'app/uninstalled'
    ];

    foreach ($topics as $topic) {
        $ch = curl_init("https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . "/webhooks.json");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Shopify-Access-Token: ' . $access_token
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'webhook' => [
                    'topic' => $topic,
                    'address' => $webhook_url,
                    'format' => 'json'
                ]
            ]),
            CURLOPT_RETURNTRANSFER => true
        ]);

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status == 201) {
            error_log("Webhook for {$topic} registered successfully!");
        } else {
            error_log("Failed to register webhook for {$topic}. Response: " . $result);
            return false;
        }
    }

    return true;
}