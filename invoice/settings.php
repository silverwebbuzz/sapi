<?php include 'header.php'; 


// Simple HTML Purifier Alternative (basic sanitization)
function sanitizeHtml($html) {
    $allowed_tags = '<html><head><style><body><<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    return strip_tags($html, $allowed_tags);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings'])) {
    $smtp_settings = [
        'host' => $_POST['smtp_host'],
        'port' => $_POST['smtp_port'],
        'from_email' => $_POST['smtp_from_email'],
        'displayname' => $_POST['smtp_displayname'],
        'username' => $_POST['smtp_user'],
        'password' => $_POST['smtp_pass'],
        'subject' => $_POST['email_subject'],
        'body' => sanitizeHtml($_POST['email_body'])
    ];
    
    $json_settings = json_encode($smtp_settings);

    // Update database
    $affectedRows = DBHelper::execute(
        "UPDATE stores SET smtp_settings = ? WHERE id = ?",
        "ss",
        [$json_settings, $shop_id]
    );

    if ($affectedRows) {
        $success_message = "Email settings saved successfully!";
    } else {
        $error_message = "Nothing to update! ";
    }
    ?>
    <script>window.location.hash = '#email';</script>
    <?php
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general_settings'])) {
    $auto_invoice_customer = isset($_POST['auto_invoice_customer']) ? 'Yes' : 'No';
    $auto_invoice_personal = isset($_POST['auto_invoice_personal']) ? 'Yes' : 'No';
    $email_invoice = $_POST['email_invoice'] ?? '';
    
    // Update database
    $affectedRows = DBHelper::execute(
        "UPDATE stores SET auto_invoice_customer = ?, auto_invoice_personal = ?, email_invoice = ? WHERE id = ?",
        "ssss",
        [$auto_invoice_customer, $auto_invoice_personal, $email_invoice, $shop_id]
    );

    if ($affectedRows) {
        $success_message = 'General settings saved successfully!';
    } else {
        $error_message = "Nothing to update! ";
    }
    ?>
    <script>window.location.hash = '#general';</script>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice_settings'])) {
    $template_id = (int)($_POST['template_id'] ?? 1);

    // Update database
    $affectedRows = DBHelper::execute(
        "UPDATE stores SET invoice_templates_id = ? WHERE id = ?",
        "is",
        [$template_id, $shop_id]
    );
    
    if ($affectedRows) {
        $success_message = 'Invoice settings saved successfully!';
    } else {
        $error_message = "Nothing to update! ";
    }
    ?>
    <script>window.location.hash = '#invoice';</script>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_logo_settings'])) {
    $upload_dir = 'uploads/logos/';  // Same level as settings.php
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $logo_url = $row['logo_url'] ?? ''; // Keep existing logo by default

    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_upload'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = 'logo_' . $shop_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old logo if exists
                if (!empty($row['logo_url'])) {
                    // Extract filename from the full URL
                    $old_filename = basename($row['logo_url']);
                    $old_file_path = $upload_dir . $old_filename;
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                $logo_url = BASE_OWNER_STORE_LOGO_URL . $new_filename;
            }
        }
    }

    // Update database
    $affectedRows = DBHelper::execute(
        "UPDATE stores SET logo_url = ? WHERE id = ?",
        "ss",
        [$logo_url, $shop_id]
    );

    if ($affectedRows) {
        $success_message = "Logo settings saved successfully!";
    } else {
        $error_message = "Nothing to update!";
    }
    ?>
    <script>window.location.hash = '#logo';</script>
    <?php
}

include 'nav.php'; 

// Default email template

$default_subject = DEFAULT_EMAIL_SUBJECT;
$default_body = DEFAULT_EMAIL_BODY;

// Fetch existing settings -- already write same code in naviga
//$sql_settings = "SELECT store_name,smtp_settings,auto_invoice_customer, auto_invoice_personal, email_invoice, email, invoice_templates_id FROM stores WHERE id = ?";
//$row = DBHelper::selectOne($sql_settings,"s", [$shop_id]);

$settings = [
    'auto_invoice_customer' => 'No',
    'auto_invoice_personal' => 'No',
    'email_invoice' => ''
];
$smtp_settings = [
    'host' => '',
    'port' => '587',
    'from_email' => '',
    'displayname' => $row['store_name'].' Sapi',
    'username' => '',
    'password' => '',
    'subject' => $default_subject,
    'body' => $default_body
];
if ($row) {
    
    // General settings
    $settings['auto_invoice_customer'] = $row['auto_invoice_customer'] ?? 'No';
    $settings['auto_invoice_personal'] = $row['auto_invoice_personal'] ?? 'No';
    $settings['email_invoice'] = $row['email_invoice'] ?? $row['email'];
    
    if (!empty($row['smtp_settings'])) {
        $stored_settings = json_decode($row['smtp_settings'], true);
        if ($stored_settings) {
            $smtp_settings = array_merge($smtp_settings, $stored_settings);
        }
    }
}
?>
<main class="main-content">
    <div class="settings-container">
        <h2>Settings</h2>

        <!-- Tab Navigation -->
        <div class="settings-tabs">
            <a href="#general" class="settings-tab active"><i class="icon-gear"></i> General</a>
            <a href="#email" class="settings-tab"><i class="icon-email"></i> Email</a>
            <a href="#invoice" class="settings-tab"><i class="icon-invoice"></i> Invoice</a>
            <a href="#logo" class="settings-tab"><i class="icon-logo"></i> Logo</a>
        </div>

        <!-- Display messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <!-- General Settings -->
            <section id="general" class="settings-section active">
                <h3>General Settings</h3>
                <form method="POST" class="general-settings-form">
                <input type="hidden" name="save_general_settings" value="1">
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
                            Send me a copy of every invoice
                        </label>
                        <p class="description">Receive a copy of every invoice at your store email address, regardless of customer email settings</p>
                        <input type="email" name="email_invoice" value="<?= htmlspecialchars($settings['email_invoice']) ?>"  placeholder="your-email@example.com" class="form-input">
                    </div>
                    <?php 
                    if($currentPlan['price']>'0'){ ?>
                    <button type="submit" class="btn-save">Save General Settings</button>
             <?php  }else {?>
                    <p>Please activate any paid plan to get using this services.</p>
            <?php   } ?>
                </form>
            </section>
            
            <!-- Email Settings -->
            <section id="email" class="settings-section">
                <h3>Email Settings</h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="save_email_settings" value="1">
                    <div class="form-group">
                        <label>From Email Address</label>
                        <input type="email" name="smtp_from_email" class="form-input" value="<?= htmlspecialchars($smtp_settings['from_email']) ?>" placeholder="Support@yourdomain.com" required>
                        <p class="description">The email address that will appear as the sender</p>
                    </div>
                    <div class="form-group">
                        <label>Email Display Name</label>
                        <input type="text" name="smtp_displayname" class="form-input"  value="<?= htmlspecialchars($smtp_settings['displayname']) ?>"  placeholder="Sapi Support" required>
                    </div>
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
                    <?php 
                    if($currentPlan['price']>'0'){ ?>
                    <button type="submit" class="btn-save">Save Email Settings</button>
                    <?php  }else {?>
                    <p>Please activate any paid plan to get using this services.</p>
                    <?php   } ?>
                    
                </form>
            </section>
            
            <!-- Invoice Settings -->
            <section id="invoice" class="settings-section">
                <h3>Invoice Settings</h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="save_invoice_settings" value="1">
                    <div class="template-selector">
                        <h4>Choose a Template</h4>
                        <div class="template-grid">

                        <?php
                            // Fetch all templates
                            $sql_template = "SELECT * FROM invoice_templates ORDER BY id";
                            $templates = DBHelper::select($sql_template);
                            
                            // Get current template ID from store
                            $current_template_id = $row['invoice_templates_id'];
                        
                            foreach ($templates as $template):
                                $is_selected = $template['id'] == $current_template_id;
                        ?>
                            <div class="template-card <?= $is_selected ? 'selected' : '' ?>">
                                <input type="radio" name="template_id" value="<?= $template['id'] ?>" id="template_<?= $template['id'] ?>" <?= $is_selected ? 'checked' : '' ?>>
                                <label for="template_<?= $template['id'] ?>">
                                    <?php if ($template['preview_image']): ?>
                                        <img src="invoice_templates/images/<?= htmlspecialchars($template['preview_image']) ?>" alt="<?= htmlspecialchars($template['template_name']) ?>">
                                    <?php else: ?>
                                        <div class="template-placeholder">
                                            <?= htmlspecialchars($template['template_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                <div class="template-name"><?= htmlspecialchars($template['template_name']) ?></div>
                            </label>
                        </div>

                    <?php 
                        endforeach; 
                    ?>   
                        </div>
                        <button type="submit" class="btn-save">Save Template</button>
                    </div>
                </form>    
            </section>

            <!-- Logo Settings -->
            <section id="logo" class="settings-section">
                <h3>Logo Settings</h3>
                <form method="POST" class="settings-form" enctype="multipart/form-data">
                    <input type="hidden" name="save_logo_settings" value="1">

                    <div class="form-group">
                        <label>Current Logo</label>
                        <?php if (!empty($row['logo_url'])): ?>
                            <div class="current-logo">
                                <img src="<?= '../' . htmlspecialchars($row['logo_url']) ?>" alt="Current Logo" style="max-height: 100px; margin: 10px 0;">
                            </div>
                        <?php else: ?>
                            <p>No logo uploaded yet. Your store name will be used instead.</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Upload New Logo</label>
                        <p class="description">Upload a new logo (recommended size: 200x100px, max size: 2MB)</p>
                        <input type="file" name="logo_upload" accept="image/*" class="form-input">
                        <p class="description">Supported formats: JPG, JPEG, PNG, GIF</p>
                    </div>

                    <button type="submit" class="btn-save">Save Logo</button>
                </form>
            </section>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>