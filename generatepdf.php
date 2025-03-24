<?php
require_once 'config.php';
require_once 'db.php';
require('fpdf/fpdf.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Invalid request method.");
}

// Retrieve GET parameters
$shop_id = $_GET['shop_id'];
$order_id = $_GET['order_id'];

$conn = DB::getInstance();
// Fetch the correct invoice table for this shop
$table_query = $conn->prepare("SELECT shop FROM stores WHERE id = ?");
$table_query->bind_param("s", $shop_id);
$table_query->execute();
$result = $table_query->get_result();

if ($result->num_rows > 0) {
    $shop_data = $result->fetch_assoc();

    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop'])); // Sanitize table name
    $invoice_table = "invoices_" . $shop_name;// Get table name

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

        // Create PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);

        // Invoice Title
        $pdf->Cell(190, 10, "Invoice #{$invoice['order_number']}", 0, 1, 'C');
        $pdf->Ln(10);

        // Customer Details
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(100, 10, "Customer: " . $invoice['customer_name']);
        $pdf->Ln(6);
        $pdf->Cell(100, 10, "Email: " . $invoice['customer_email']);
        $pdf->Ln(10);

        // Billing Address
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(100, 10, "Billing Address:");
        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(190, 10, implode(", ", $billing_address));
        $pdf->Ln(10);

        // Shipping Address
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(100, 10, "Shipping Address:");
        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(190, 10, implode(", ", $shipping_address));
        $pdf->Ln(10);

        // Order Details Table Header
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 10, "Product", 1);
        $pdf->Cell(30, 10, "Qty", 1);
        $pdf->Cell(40, 10, "Price", 1);
        $pdf->Cell(40, 10, "Total", 1);
        $pdf->Ln();

        // Order Items
        $pdf->SetFont('Arial', '', 12);
        foreach ($products as $product) {
            $pdf->Cell(80, 10, $product['name'], 1);
            $pdf->Cell(30, 10, $product['quantity'], 1);
            $pdf->Cell(40, 10, $product['price'], 1);
            $pdf->Cell(40, 10, number_format($product['quantity'] * $product['price'], 2), 1);
            $pdf->Ln();
        }

        // Invoice Total
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(110, 10, "", 0);
        $pdf->Cell(40, 10, "Total:", 1);
        $pdf->Cell(40, 10, $invoice['total_price'], 1);
        $pdf->Ln();

        // Output PDF
        $pdf->Output('D', "Invoice_{$invoice['order_number']}.pdf"); // Force download
    } else {
        echo "Invoice not found for order_id: $order_id.";
    }
} else {
    echo "Invalid shop_id: $shop_id.";
}

$conn->close();
?>