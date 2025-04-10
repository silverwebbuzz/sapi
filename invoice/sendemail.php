<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
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

$shop_data = DBHelper::selectOne(
    "SELECT * FROM stores WHERE `id` = ? ",
    "s", 
    [$shop_id]
);

if ($shop_data) {
    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
    $invoice_table = "invoices_" . $shop_name;
    
    // Fetch invoice details
    $invoice = DBHelper::selectOne(
        "SELECT * FROM `$invoice_table` WHERE order_id = ?",
        "s", 
        [$order_id]
    );

    if ($invoice) {
        
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

        header("location:javascript://history.go(-1)");
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