<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
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
        
        if( $_GET['invoicestatus']=='generated')
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
        $pdf_content = $pdf->Output('invoice_'.$invoice['order_name'].'.pdf', 'I');
        $pdf_content = $pdf->Output('', 'S');
        $encoded_pdf = base64_encode($pdf_content); // Encode PDF for storage


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

        //header("location:javascript://history.go(-1)");
    } else {
        die("No invoice found with the specified order ID.");
    }
} else {
    die("No shop found with the specified ID.");
}

?>