<?php
require_once '../config.php';
require_once '../db.php';
$conn = DB::getInstance();

require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once '../vendor/autoload.php';

 // Import PHPMailer classes
 use PHPMailer\PHPMailer\PHPMailer;
 use PHPMailer\PHPMailer\SMTP;
 use PHPMailer\PHPMailer\Exception;

require_once '../invoice/helper.php';
 
$shop = 'silverwebbuzzapp.myshopify.com';
$order_id = '6303427920172';

    $table_query = $conn->prepare("SELECT * FROM stores WHERE shop = ?");
    $table_query->bind_param("s", $shop);
    $table_query->execute();
    $result = $table_query->get_result();
    $shop_data = $result->fetch_assoc();
    $shop_id = $shop_data['id'];

    $generatepdf  = generatepdf($shop_id,$order_id);
    $sendemail  = sendemail($shop_id,$order_id);
    
    $results_pdf_email =$generatepdf.$sendemail;
    // webhook logs.
    $insertSql = "INSERT INTO webhook (shop, topic, orders, cdate) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    // Bind parameters: 'shop' and 'topic' are strings, 'orders' is a string, and 'cdate' is a string representation of datetime.
    $stmt->bind_param("ssss", $shop, $topic, $results_pdf_email , $cdate);

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