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
        
        $decoded_pdf = base64_decode($invoice['pdf_invoice']);


        $smtp_settings = json_decode($shop_data['smtp_settings'], true);

        echo "<pre>";
        print_r($smtp_settings);
        exit;

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
            $decoded_pdf,
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