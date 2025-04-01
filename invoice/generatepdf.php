<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config.php';
require_once '../db.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once '../vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Invalid request method.");
}

// Retrieve GET parameters
$shop_id = $_GET['shop_id'];
$order_id = $_GET['order_id'];

$conn = DB::getInstance();

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

        // Prepare company information
        //$company_name = "Silver WebBuzz Pvt. Ltd.";
        //$company_address = "1109, Satyamev Eminence, Science City Road, Sola, Ahmedabad, Gujarat 380060";
        //$company_phone = "+91 1234567890";
        //$company_email = "accounts@silverwebbuzz.com";
        
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
            $items_html .= '<td>'.$counter.'</td>';
            $items_html .= '<td>'.$item['name'].'</td>';
            $items_html .= '<td>-</td>'; // HSN/SAC code would go here
            $items_html .= '<td>'.$item['quantity'].'</td>';
            $items_html .= '<td class="text-right">'.$invoice['currency'].' '.number_format($item['price'], 2).'</td>';
            $items_html .= '<td class="text-right">'.$tax_rate.'%</td>';
            $items_html .= '<td class="text-right">'.$invoice['currency'].' '.number_format($tax_amount, 2).'</td>';
            $items_html .= '<td class="text-right">'.$invoice['currency'].' '.number_format($item['price'] * $item['quantity'], 2).'</td>';
            $items_html .= '</tr>';
            $counter++;
        }

        // Prepare replacements array
        $replacements = [
            '{{Company_Logo}}' => $shop_data['logo_url'] ? '<img src="'.$shop_data['logo_url'].'" class="logo">' : '',
            '{{Company_Name}}' => $shop_data['store_name'],
            '{{Company_Address}}' => $shop_data['address1']."<br/>".$shop_data['address2']."<br/>".$shop_data['city']." ".$shop_data['province']." ".$shop_data['province_code']." ".$shop_data['zip']."<br/>".$shop_data['country_name'] ,
            '{{Company_Phone}}' => $shop_data['phone'],
            '{{Company_Email}}' => $shop_data['email'],
            '{{Company_GSTIN}}' => $shop_data['gstin'] ?? '',
            '{{Order_Number}}' => $invoice['order_number'],
            '{{Invoice_Date}}' => date('d/m/Y', strtotime($invoice['created_at'])),
            '{{Due_Date}}' => date('d/m/Y', strtotime($invoice['created_at'].' +15 days')),
            '{{Billing_Name}}' => $billing_address['name'] ?? '',
            '{{Billing_Address1}}' => $billing_address['address1'] ?? '',
            '{{Billing_Address2}}' => $billing_address['address2'] ?? '',
            '{{Billing_City}}' => $billing_address['city'] ?? '',
            '{{Billing_State}}' => $billing_address['province'] ?? '',
            '{{Billing_Zip}}' => $billing_address['zip'] ?? '',
            '{{Billing_Country}}' => $billing_address['country'] ?? '',
            '{{Billing_GSTIN}}' => '', // Add GSTIN if available
            '{{Shipping_Name}}' => isset($shipping_address['name']) ? $shipping_address['name'] : ($billing_address['name'] ?? ''),
            '{{Shipping_Address1}}' => isset($shipping_address['address1']) ? $shipping_address['address1'] : ($billing_address['address1'] ?? ''),
            '{{Shipping_Address2}}' => isset($shipping_address['address2']) ? $shipping_address['address2'] : ($billing_address['address2'] ?? ''),
            '{{Shipping_City}}' => isset($shipping_address['city']) ? $shipping_address['city'] : ($billing_address['city'] ?? ''),
            '{{Shipping_State}}' => isset($shipping_address['province']) ? $shipping_address['province'] : ($billing_address['province'] ?? ''),
            '{{Shipping_Zip}}' => isset($shipping_address['zip']) ? $shipping_address['zip'] : ($billing_address['zip'] ?? ''),
            '{{Shipping_Country}}' => isset($shipping_address['country']) ? $shipping_address['country'] : ($billing_address['country'] ?? ''),
            '{{Order_Items}}' => $items_html,
            '{{Subtotal}}' => $invoice['currency'].' '.number_format($invoice['subtotal_price'], 2),
            '{{Tax_Amount}}' => $invoice['currency'].' '.number_format($invoice['tax_amount'], 2),
            '{{Shipping_Cost}}' => $invoice['currency'].' '.number_format($invoice['shipping_cost'], 2),
            '{{Discount_Amount}}' => $invoice['currency'].' '.number_format($invoice['discount_amount'], 2),
            '{{Total_Amount}}' => $invoice['currency'].' '.number_format($invoice['total_price'], 2),
            '{{Payment_Method}}' => $invoice['payment_method'] ?? 'Unknown',
            '{{Payment_Status}}' => ucfirst($invoice['order_status'])
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
        $pdf->SetAuthor($company_name);
        $pdf->SetTitle('Invoice '.$invoice['order_number']);
        $pdf->SetSubject('Invoice');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Add a page
        $pdf->AddPage();

        // Output HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Close and output PDF document
        $pdf->Output('invoice_'.$invoice['order_number'].'.pdf', 'I'); exit;
        $pdf_content = $pdf->Output('', 'S');
        $encoded_pdf = base64_encode($pdf_content); // Encode PDF for storage


$to_email = $invoice['customer_email'];
$to_name = $invoice['customer_name'];
$subject = "Invoice #{$invoice['order_number']} from Your Store";
$body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .header { color: #333366; }
            .footer { margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <h2 class=\"header\">Dear {$to_name},</h2>
        <p>Thank you for your order! Please find your invoice #{$invoice['order_number']} attached.</p>
        <p><strong>Order Total:</strong> {$invoice['currency']} {$invoice['total_price']}</p>
        <p><strong>Date:</strong> {$invoice['created_at']}</p>
        <div class=\"footer\">
            <p>If you have any questions, please contact our support team.</p>
            <p>Thank you for your business!</p>
        </div>
    </body>
    </html>
";
//When sending an email, you would replace the variables like this:
/*$email_body = str_replace(
    ['{invoice_number}', '{customer_name}', '{total_price}', '{currency}', '{created_at}'],
    [$invoice['order_number'], $customer_name, $invoice['total_price'], $invoice['currency'], $invoice['created_at']],
    $smtp_settings['body']
);*/

        // Send email with attachment
        $email_sent = sendEmailWithAttachment(
            $to_email,
            $to_name,
            $subject,
            $body,
            $pdf_content,
            "invoice_{$invoice['order_number']}.pdf"
        );

        // Single database update for both PDF and email status
        $update_stmt = $conn->prepare("UPDATE `$invoice_table` 
        SET 
            invoice_status = 'generated',
            pdf_invoice = ?,
            email_status = ?
        WHERE order_id = ?
        ");

        $email_status = $email_sent ? 'sent' : 'failed';
        $update_stmt->bind_param("sss", $encoded_pdf, $email_status, $order_id);
        $update_stmt->execute();

    } else {
        die("No invoice found with the specified order ID.");
    }
} else {
    die("No shop found with the specified ID.");
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
?>