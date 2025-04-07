<?php
require_once '../config.php';
require_once '../db.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once '../vendor/autoload.php';

 // Import PHPMailer classes
 use PHPMailer\PHPMailer\PHPMailer;
 use PHPMailer\PHPMailer\SMTP;
 use PHPMailer\PHPMailer\Exception;

// Verify webhook HMAC
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
$data = file_get_contents('php://input');
$calculated_hmac = base64_encode(hash_hmac('sha256', $data, SHOPIFY_API_SECRET, true));

if (!hash_equals($hmac, $calculated_hmac)) {
    http_response_code(401);
    die('Invalid webhook HMAC');
}

// Decode webhook data
$order = json_decode($data, true);
$shop = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
$topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC']; // Get webhook topic
$cdate = date("Y-m-d H:i:s");

$conn = DB::getInstance();

// webhook logs.
$insertSql = "INSERT INTO webhook (shop, topic, orders, cdate) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);
// Bind parameters: 'shop' and 'topic' are strings, 'orders' is a string, and 'cdate' is a string representation of datetime.
$stmt->bind_param("ssss", $shop, $topic, $data, $cdate);
if (!$stmt->execute()) {
    die("SQL Error: " . $stmt->error);
}
// webhook logs end.

if ($topic === 'app/uninstalled') {
    // Handle App Uninstall
    $stmt = $conn->prepare("UPDATE stores SET status = 'uninstalled' WHERE shop = ?");
    $stmt->bind_param("s", $shop);
    if ($stmt->execute()) {
        error_log("Store {$shop} marked as uninstalled.");
    } else {
        error_log("Failed to update store status: " . $stmt->error);
    }
} elseif ($topic === 'orders/create') {

    // Sanitize shop name to match table name
    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop));
    $invoice_table = "invoices_" . $shop_name;

    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$invoice_table'");
    if ($table_check->num_rows === 0) {
        die("Invoice table not found for store.");
    }

    // Insert orders into the database
    $stmt = $conn->prepare("
    INSERT INTO `$invoice_table` 
    (order_id, order_number, order_name, customer_name, customer_email, billing_address, shipping_address, currency, subtotal_price, total_price, tax_amount, discount_amount, shipping_cost, invoice_status, email_status, payment_method, order_status, products) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
        order_name = VALUES(order_name),
        customer_name = VALUES(customer_name),
        customer_email = VALUES(customer_email),
        billing_address = VALUES(billing_address),
        shipping_address = VALUES(shipping_address),
        currency = VALUES(currency),
        subtotal_price = VALUES(subtotal_price),
        total_price = VALUES(total_price),
        tax_amount = VALUES(tax_amount),
        discount_amount = VALUES(discount_amount),
        shipping_cost = VALUES(shipping_cost),
        invoice_status = VALUES(invoice_status),
        email_status = VALUES(email_status),
        payment_method = VALUES(payment_method),
        order_status = VALUES(order_status),
        products = VALUES(products)
    ");

    $order_id = $order['id']; 
    $order_number = $order['order_number'];
    $order_name = $order['name'];
    $customer_name = $order['customer']['first_name'] . ' ' . $order['customer']['last_name'];
    $customer_email = $order['customer']['email'];
    $currency = $order['currency'];
    $subtotal_price = $order['subtotal_price'];
    $total_price = $order['total_price'];
    $tax_amount = isset($order['total_tax']) ? $order['total_tax'] : 0.00;
    $discount_amount = isset($order['total_discounts']) ? $order['total_discounts'] : 0.00;
    $shipping_cost = isset($order['total_shipping_price_set']['shop_money']['amount']) ? $order['total_shipping_price_set']['shop_money']['amount'] : 0.00;
    $billing_address = json_encode($order['billing_address'] ?? []);
    $shipping_address = json_encode($order['shipping_address'] ?? []);
    $payment_method = $order['gateway'] ?? 'Unknown';
    $order_status = $order['financial_status'] ?? 'pending';
    $products = json_encode($order['line_items'], JSON_UNESCAPED_UNICODE);


    $stmt->bind_param("ssssssssdddddsss", 
    $order_id,
    $order_number,
    $order_name,
    $customer_name, 
    $customer_email, 
    $billing_address, 
    $shipping_address, 
    $currency, 
    $subtotal_price, 
    $total_price, 
    $tax_amount, 
    $discount_amount, 
    $shipping_cost,
    $payment_method,
    $order_status,
    $products
    );

    if ($stmt->execute()) {
        error_log("Invoice created for Order ID: {$order_id}.");
    } else {
        error_log("Error inserting invoice: " . $stmt->error);
    }

    $table_query = $conn->prepare("SELECT * FROM stores WHERE shop = ?");
    $table_query->bind_param("s", $shop);
    $table_query->execute();
    $result = $table_query->get_result();
    $shop_data = $result->fetch_assoc();
    $shop_id = $shop_data['id'];
    

    // Fetch invoice details
    $stmt = $conn->prepare("SELECT * FROM `$invoice_table` WHERE order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $invoice_result = $stmt->get_result();
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
    $template = file_get_contents('temp/'.$template_html['template_file']);
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


    $decoded_pdf = $pdf_content;
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
    
} else {
    error_log("Unhandled webhook topic: {$topic}");
}

// Respond to Shopify
http_response_code(200);