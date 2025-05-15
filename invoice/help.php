<?php include 'header.php';
include 'nav.php'; 
?>
<style>
        
        p {
        line-height: 1.4;
        margin-bottom: 10px;
        margin-top: 10px;
        font-size: 15px;
        }
        h2,  h3, h4 {
        margin-top: 20px;
        margin-bottom: 10px;
        }
        h4{
            font-size: 15px;
        }
        ul {
            padding-left: 20px;
            font-size: 15px;
        }
        li {
            margin-bottom: 10px;
            font-size: 15px;
        }
        .video-container embed {
            width: 100%;
            max-width: 800px;
            height: 400px;
            border: none;
        }
        .support-contact {
            margin-top: 40px;
            font-size: 15px;
            text-align: center;
        }
        .support-contact a {
            color: #007acc;
            text-decoration: none;
        }
    </style>
<main class="main-content">
    
    <div class="package-card">
        <h1>Need Help Using Our App?</h1>
        <p>We're Here to Support You - Quickly & Easily</p>

        <p>Get step-by-step guidance on using the app’s features like invoice generation, templates, and subscription management. Whether you're stuck or just need tips, we’re always available to assist.</p>

        <h2>Support Options:</h2>
        <ul>
            <li>Watch our quick start video for an overview</li>
            <li>Follow our simple usage instructions</li>
            <li>Contact our support team anytime for personalized help</li>
        </ul>

        <p>We're committed to making your experience smooth and productive.</p>
        
        <h2>Watch our quick start video for an overview</h2>
        <div class="video-container">
        <embed src="https://www.youtube.com/embed/QLqbBeDTs5E" type="video/mp4" style="width:800px; height:400px;" />
        </div>

        <h2>Follow Our Simple Usage Instructions</h2>
        
        <h3>1. Free Plan Features</h3>
        <ul>
            <li>View your order list directly on the dashboard or through the Shopify Orders page.</li>
            <li>Manually generate invoices and email them to your customers.</li>
            <li>Customize your invoice design from the <strong>Settings > Invoice</strong> tab by selecting your preferred template.</li>
            <li>Easily generate, print, download PDF invoices, or resend them to customers as needed.</li>
        </ul>

        <h3>2. Premium Plan Features (Includes 7-Day Free Trial)</h3>
        <p><strong>Upgrade to unlock automation and advanced customization:</strong></p>

        <h4>a) Automatic Invoice Delivery</h4>
        <ul>
            <li>Go to <strong>Settings > General</strong> to enable automatic invoice generation.</li>
            <li>Choose to send invoices to customers, store owners, or a custom email address.</li>
            <li>Once enabled, the system will automatically create and email PDF invoices immediately after an order is placed.</li>
        </ul>

        <h4>b) Custom Email Configuration</h4>
        <ul>
            <li>Navigate to <strong>Settings > Email</strong> to configure your own SMTP settings.</li>
            <li>Customize the email template layout and content to match your brand.</li>
            <li>Ensure customers receive professional, personalized invoice emails with every order.</li>
        </ul>

        

        <div class="support-contact">
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