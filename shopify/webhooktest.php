<?php
require_once '../config.php';
require_once '../db.php';
$conn = DB::getInstance();
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once '../vendor/autoload.php';
require_once 'shopify_functions.php';

 // Import PHPMailer classes
 use PHPMailer\PHPMailer\PHPMailer;
 use PHPMailer\PHPMailer\SMTP;
 use PHPMailer\PHPMailer\Exception;


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
