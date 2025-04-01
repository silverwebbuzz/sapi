<?php include 'header.php';
include 'nav.php'; 
$shop_id = 1;
$store['shop'] = 'silverwebbuzzapp.myshopify.com';
//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));
$invoices_query = $conn->query("SELECT * FROM `$invoice_table` ORDER BY created_at DESC");
?>

<main class="main-content">
    <div class="page-header">
        <h2>Shopify Orders List</h2>
        <h3>Display All Shopify Orders with all invoice and email status.</h3>
        <!--div class="actions">
            <button class="btn-export">Export CSV</button>
            <button class="btn-filter">Filter Orders</button>
        </div-->
    </div>

    <div class="orders-card">
        <table id="ordersTable" class="display">
            <thead>
                <tr>
                    <th>ORDER ID</th>
                    <th>DATE</th>
                    <th>CUSTOMER</th>
                    <th>AMOUNT</th>
                    <th>STATUS</th>
                    <th>INVOICE</th>
                    <th>EMAIL</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($invoice = $invoices_query->fetch_assoc()): ?>
                <tr>
                    <td><?= $invoice['order_number'] ?></td>
                    <td><?= $invoice['created_at'] ?></td>
                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    <td>$<?= number_format($invoice['total_price'], 2) ?></td>
                    <td><span class="status completed"><?= ucfirst($invoice['invoice_status']) ?></span></td>
                    <td><button class="btn-action download">Generate</button><a href="<?= BASE_URL ?>/invoice/generatepdf.php?shop_id=<?= $shop_id ?>&order_id=<?= $invoice['order_id'] ?>" class="view-invoice">View</a></td>
                    <td>
                        <button class="btn-action resend">Resend</button>
                    </td>
                </tr>
                
            <?php endwhile; ?>
                <tr>
                    <td>1001</td>
                    <td>2025-03-28 10:30:45</td>
                    <td>John Smith</td>
                    <td>$125.99</td>
                    <td><span class="status completed">Completed</span></td>
                    <td><a href="#" class="view-invoice">View</a></td>
                    <td>
                        <button class="btn-action resend">Resend</button>
                        <button class="btn-action download">Download</button>
                    </td>
                </tr>
                <!-- More rows would be loaded from database -->
            </tbody>
        </table>
    </div>
</main>

<?php include 'footer.php'; ?>