<?php include 'header.php';
include 'nav.php'; 
$shop_id = 1;
$store['shop'] = 'silverwebbuzzapp.myshopify.com';
//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));
$invoices_query = DBHelper::select("SELECT * FROM `$invoice_table` ORDER BY created_at DESC","",[]);
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
                    <th>INVOICE</th>
                    <th>EMAIL</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <!-- More rows would be loaded from database -->
            <?php foreach($invoices_query as $invoice) : ?>
                <tr>
                    <td><?= $invoice['order_number'] ?></td>
                    <td><?= $invoice['created_at'] ?></td>
                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    <td><?= $invoice['currency']?> <?= number_format($invoice['total_price'], 2) ?></td>
                    <td><span class="status completed"><?= ucfirst($invoice['invoice_status']) ?></span></td>
                    <td><span class="status completed"><?= ucfirst($invoice['email_status']) ?></span></td>
                    <td>
                    <?php if ($invoice['pdf_invoice']!=''){ ?>
                            <a href="#" class="view-invoice-btn" data-invoice-id="<?= $invoice['pdf_invoice']; ?>">View Invoice</a>
                            <a href="#" onclick="sendEmail(<?= $shop_id ?>, <?= $invoice['order_id'] ?>, '<?= $invoice['email_status'] ?>'); return false;">
                            <?= ($invoice['email_status'] == 'pending') ? 'Send Email' : 'Resend Email'; ?>
                            </a>
                        <?php } else { ?>
                            <a href="#" onclick="generateInvoice(<?= $shop_id ?>, <?= $invoice['order_id'] ?>, '<?= $invoice['invoice_status'] ?>'); return false;" class="view-invoice">
                            <?= ($invoice['invoice_status'] == 'pending') ? 'Generate Invoice' : 'View Invoice'; ?>
                            </a>
                        <?php } ?>
                    </td>
                </tr>
            <?php endforeach; ?>
                
                
            </tbody>
        </table>
    </div>
</main>
<!-- Message Box-->
<div id="message-box" style="display:none; position:fixed; top:20px; right:20px; padding:10px 20px; border-radius:5px; color:#fff; z-index:9999; font-weight:bold;"></div>

<!-- Modal Wrapper -->
<div id="invoiceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999;">
  <div style="position:relative; width:80%; max-width:800px; margin:5% auto; background:#fff; padding:20px;">
    <span style="position:absolute; top:10px; right:20px; cursor:pointer;" onclick="closeInvoiceModal()">✖</span>
    <embed id="invoiceFrame" type="application/pdf" style="width:100%; height:600px; border:none;" />
  </div>
</div>

<?php include 'footer.php'; ?>