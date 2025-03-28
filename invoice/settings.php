<?php include 'header.php'; 
include 'nav.php'; 

// Default email template
$default_subject = "Invoice #{invoice_number} from Your Store";
$default_body = '
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .header { color: #333366; }
        .footer { margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <h2 class="header">Dear {customer_name},</h2>
    <p>Thank you for your order! Please find your invoice #{invoice_number} attached.</p>
    <p><strong>Order Total:</strong> {currency} {total_price}</p>
    <p><strong>Date:</strong> {created_at}</p>
    <div class="footer">
        <p>If you have any questions, please contact our support team.</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
';

// Fetch existing settings
$table_query = $conn->prepare("SELECT smtp_settings,auto_invoice_customer, auto_invoice_personal, email_invoice, email  FROM stores WHERE id = ?");
$table_query->bind_param("s", $shop_id);
$table_query->execute();
$result = $table_query->get_result();

$settings = [
    'auto_invoice_customer' => 'No',
    'auto_invoice_personal' => 'No',
    'email_invoice' => ''
];
$smtp_settings = [
    'host' => '',
    'port' => '587',
    'username' => '',
    'password' => '',
    'subject' => $default_subject,
    'body' => $default_body
];
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // General settings
    $settings['auto_invoice_customer'] = $row['auto_invoice_customer'] ?? 'No';
    $settings['auto_invoice_personal'] = $row['auto_invoice_personal'] ?? 'No';
    $settings['email_invoice'] = $row['email_invoice'] ?? $row['email'];
    
    // SMTP settings
    if (!empty($row['smtp_settings'])) {
        $smtp_settings = json_decode($row['smtp_settings'], true);
        if ($smtp_settings) {
            $settings['smtp'] = array_merge($settings['smtp'], $smtp_settings);
        }
    }
}
?>
<main class="main-content">
    <div class="settings-container">
        <h2>Settings</h2>

        <!-- Display messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); // Clear the message after displaying ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); // Clear the message after displaying ?>
        <?php endif; ?>

        <!-- Tab Navigation -->

        <div class="settings-tabs">
            <a href="#general" class="settings-tab active"><i class="icon-gear"></i> General</a>
            <a href="#email" class="settings-tab"><i class="icon-email"></i> Email</a>
            <a href="#invoice" class="settings-tab"><i class="icon-invoice"></i> Invoice</a>
        </div>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <!-- General Settings -->
            <section id="general" class="settings-section active">
                <h3>General Settings</h3>
                <form class="general-settings-form" data-section="general">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="auto_invoice_customer" <?= $settings['auto_invoice_customer'] === 'Yes' ? 'checked' : '' ?>>
                            Automatic invoices to customers
                        </label>
                        <p class="description">Enable/disable sending invoices to customers automatically</p>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="auto_invoice_personal"  <?= $settings['auto_invoice_personal'] === 'Yes' ? 'checked' : '' ?>>
                            Automatic personal copy
                        </label>
                        <p class="description">Receive a copy of every invoice automatically</p>
                        <input type="email" name="email_invoice" value="<?= htmlspecialchars($settings['email_invoice']) ?>"  placeholder="your-email@example.com" class="form-input">
                    </div>
                    <button type="submit" class="btn-save">Save General Settings</button>
                </form>
            </section>
            
            <!-- Email Settings -->
            <section id="email" class="settings-section">
                <h3>Email Settings</h3>
                <form method="POST" action="save_smtp_settings.php" class="email-settings-form">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-input"  value="<?= htmlspecialchars($smtp_settings['host']) ?>"  placeholder="smtp.example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-input" value="<?= htmlspecialchars($smtp_settings['port']) ?>"  placeholder="587" required>
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_user" class="form-input" value="<?= htmlspecialchars($smtp_settings['username']) ?>" placeholder="your-email@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_pass" class="form-input"  value="<?= htmlspecialchars($smtp_settings['password']) ?>" placeholder="••••••••" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Subject</label>
                        <input type="text" name="email_subject" class="form-input" value="<?= htmlspecialchars($smtp_settings['subject']) ?>" placeholder="Invoice Notification" required>
                        <p class="description">Available variables: {invoice_number}, {customer_name}, {total_price}, {currency}, {created_at}</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Body (HTML)</label>
                        <textarea name="email_body" class="form-input" rows="12" required><?= htmlspecialchars($smtp_settings['body']) ?></textarea>
                        <p class="description">Available variables: {invoice_number}, {customer_name}, {total_price}, {currency}, {created_at}</p>
                    </div>
                    
                    <button type="submit" class="btn-save">Save Email Settings</button>
                </form>
            </section>
            
            <!-- Invoice Settings -->
            <section id="invoice" class="settings-section">
                <h3>Invoice Settings</h3>
                <div class="template-selector">
                    <h4>Choose a Template</h4>
                    <div class="template-grid">
                        <div class="template-card selected">
                            <img src="templates/template1.jpg" alt="Template 1">
                            <div class="template-name">Modern</div>
                        </div>
                        <div class="template-card">
                            <img src="templates/template2.jpg" alt="Template 2">
                            <div class="template-name">Classic</div>
                        </div>
                        <div class="template-card">
                            <img src="templates/template3.jpg" alt="Template 3">
                            <div class="template-name">Minimal</div>
                        </div>
                        <div class="template-card">
                            <img src="templates/template4.jpg" alt="Template 4">
                            <div class="template-name">Elegant</div>
                        </div>
                        <div class="template-card">
                            <img src="templates/template5.jpg" alt="Template 5">
                            <div class="template-name">Professional</div>
                        </div>
                        <div class="template-card">
                            <img src="templates/template6.jpg" alt="Template 6">
                            <div class="template-name">Creative</div>
                        </div>
                    </div>
                    <button class="btn-save">Save Template</button>
                </div>
            </section>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>