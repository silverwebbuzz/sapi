<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once '../vendor/autoload.php';



if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Invalid request method.");
}

// Retrieve GET parameters
$shop_id = $_GET['shop_id'];
$order_id = $_GET['order_id'];
sendemail($shop_id,$order_id);
exit;

//below code is in helper
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

?>