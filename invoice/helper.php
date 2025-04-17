<?php
// Import Dom PDF classes
use Dompdf\Dompdf;
use Dompdf\Options;

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function generatepdf($shop_id,$order_id){
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ? ",
        "s", 
        [$shop_id]
    );
    
    if ($shop_data) {
        $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
        $invoice_table = "invoices_" . $shop_name;
    
        
        // Fetch invoice details
        $invoice = DBHelper::selectOne("SELECT * FROM `$invoice_table` WHERE order_id = ?","s", [$order_id]);
    
        if ($invoice) {
            
            if(isset($_GET['invoicestatus']) && $_GET['invoicestatus']=='generated')
            {
                ?>
                <embed src="data:application/pdf;base64,<?= $invoice['pdf_invoice']; ?>" type="application/pdf" width="100%" height="100%" />
                <?php
                exit;
            }
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
                
                $items_html .= '<tr style="font-size: 10px; border-bottom: 1px solid #ddd">';
                $items_html .= '<td style="text-align: left">'.$item['name'].'</td>';
                $items_html .= '<td style="text-align: left">'.$item['variant_title'].'</td>';
                $items_html .= '<td style="text-align: left">'.$invoice['currency'].' '.number_format($item['price'], 2).'</td>';
                $items_html .= '<td style="text-align: left">'.$item['quantity'].'</td>';
                //$items_html .= '<td class="text-right">'.$tax_rate.'%</td>';
                //$items_html .= '<td class="text-right">'.$invoice['currency'].' '.number_format($tax_amount, 2).'</td>';
                $items_html .= '<td style="text-align: left">'.$invoice['currency'].' '.number_format($item['price'] * $item['quantity'], 2).'</td>';
                $items_html .= '</tr>';
                $counter++;
            }
    
            // Prepare replacements array
            $replacements = [
                '{{ Company_Logo }}' => $shop_data['logo_url'],
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
            $template_html = DBHelper::selectOne(
                "SELECT * FROM `invoice_templates` WHERE id = ?",
                "s", 
                [$template_id]
            );
            
            $template = file_get_contents('invoice_templates/html/'.$template_html['template_file']);
            $html = str_replace(array_keys($replacements), array_values($replacements), $template);
    
            // Create new PDF document
            // Set options (optional but useful)
            $options = new Options();
            $options->set('isRemoteEnabled', true); // Enable external images (if needed)
            $options->set('defaultFont', 'DejaVu Sans'); // Set default font

            // Initialize DomPDF with options
            $dompdf = new Dompdf($options);

            // Load HTML content
            $dompdf->loadHtml($html);

            // (Optional) Set paper size and orientation
            $dompdf->setPaper('A4', 'portrait'); // or 'landscape'

            // Render the HTML as PDF
            $dompdf->render();

            // Output PDF to browser
            //$dompdf->stream('invoice_'.$invoice['order_name'].'.pdf', array("Attachment" => false));

            // Or to string
            $pdf_output = $dompdf->output();
            $encoded_pdf = base64_encode($pdf_output); // Encode for storage
     
            // Single database update for both PDF and email status
            $affectedRows = DBHelper::execute(
                "UPDATE `$invoice_table` SET  invoice_status = 'generated', pdf_invoice = ? WHERE order_id = ? ",
                "ss",
                [$encoded_pdf, $order_id]
            );
            $affectedRows = DBHelper::execute(
                "UPDATE `store_subscriptions` SET  order_used = order_used+1  WHERE store_id = ? ",
                "s",
                [$shop_id]
            );
            return "PDF Generated Successfully.";

        } else {
            return "No invoice found with the specified order ID.";
        }
    } else {
        return "No shop found with the specified ID.";
    }
}

function sendemail($shop_id,$order_id){
        
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ? ",
        "s", 
        [$shop_id]
    );

    if ($shop_data) {
        $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
        $invoice_table = "invoices_" . $shop_name;
        
        // Fetch invoice details
        $invoice = DBHelper::selectOne("SELECT * FROM `$invoice_table` WHERE order_id = ?","s", [$order_id]);

        if ($invoice) {
            
            $decoded_pdf = base64_decode($invoice['pdf_invoice']);
            $billing_address = json_decode($invoice['billing_address'], true);

            // SMTP settings 
            if($shop_data['smtp_settings']!=''){
                $smtp_settings = json_decode($shop_data['smtp_settings'], true);
            }else{
                $default_subject = DEFAULT_EMAIL_SUBJECT;
                $default_body = DEFAULT_EMAIL_BODY;
                $smtp_settings = [
                    'host' => 'mail.silverwebbuzz.com',
                    'port' => '587',
                    'displayname' => $shop_data['store_name'].' - Sapi',
                    'username' => 'support.sapi@silverwebbuzz.com',
                    'password' => 'Bhavik@1109',
                    'subject' => $default_subject,
                    'body' => $default_body
                ];
            }
            
            $to_email = $invoice['customer_email'];
            $to_name = $invoice['customer_name'];
            $subject = str_replace(['{invoice_number}','{shop_name}'],[$invoice['order_name'],$shop_data['store_name']],$smtp_settings['subject']);
            $body = $smtp_settings['body'];
            //When sending an email, you would replace the variables like this:
            $email_body = str_replace(
                ['{invoice_number}', '{customer_name}', '{total_price}', '{currency}', '{created_at}'],
                [$invoice['order_name'], $invoice['customer_name'], $invoice['total_price'], $invoice['currency'], $invoice['created_at']],
                $body
            );

            // Send email with attachment
            $email_sent = sendEmailWithAttachment($smtp_settings, $to_email,$to_name, $subject, $email_body, $decoded_pdf, "invoice_{$invoice['order_name']}.pdf");
            $email_status = $email_sent ? 'sent' : 'failed';

            // Single database update for both PDF and email status
            $affectedRows = DBHelper::execute(
                "UPDATE `$invoice_table` SET email_status = ? WHERE order_id = ? ",
                "ss",
                [$email_status, $order_id]
            );
            $affectedRows = DBHelper::execute(
                "UPDATE `store_subscriptions` SET  email_used = email_used+1  WHERE store_id = ? ",
                "s",
                [$shop_id]
            );

            return "Email Status : ". $email_status;
        } else {
            return "No invoice found with the specified order ID.";
        }
    } else {
        return "No shop found with the specified ID.";
    }
}

// Email sending function
function sendEmailWithAttachment($smtp_settings, $to_email, $to_name, $subject, $html_body, $attachment_content, $attachment_name) {
    
    $mail = new PHPMailer(true);
 
     try {
         // Server settings
         //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
         $mail->isSMTP();
         $mail->Host       = $smtp_settings['host'];
         $mail->SMTPAuth   = true;
         $mail->Username   = $smtp_settings['username'];
         $mail->Password   = $smtp_settings['password'];
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
         $mail->Port       = $smtp_settings['port'];
         $mail->SMTPKeepAlive = true;
 
         // Critical headers
         $mail->setFrom($smtp_settings['username'], $smtp_settings['displayname'], true);
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
 
     } catch (Exception $e) {
         echo "Error: " . $e->getMessage();
         error_log("Mail Error: " . $e->getMessage());
         return false;
         
     }
 }

?>