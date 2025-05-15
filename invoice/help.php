<?php include 'header.php';
include 'nav.php'; 
?>

<main class="main-content">
    <div class="page-header">
        <h2>Help</h2>
        <h3>Here you can find all the help you need to use the app.</h3>
        <!--div class="actions">
            <button class="btn-export">Export CSV</button>
            <button class="btn-filter">Filter Orders</button>
        </div-->
    </div>

    <div class="orders-card">
    <h1>How to Use the Shopify App</h1>
        <ol>
        <li>Install the app from the Shopify App Store.</li>
        <li>Authorize your store during installation.</li>
        <li>Use the sidebar navigation to access Dashboard, Templates, and Settings.</li>
        <li>Make sure to activate your subscription plan to unlock all features.</li>
        </ol>

        <embed src="https://www.youtube.com/embed/QLqbBeDTs5E" type="video/mp4" style="width:100%; height:400px;" />

        <div class="support">
        Need help? Email us at <a href="mailto:support.sapi@silverwebbuzz.com">support.sapi@silverwebbuzz.com</a>
        </div>
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