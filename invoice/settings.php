<?php include 'header.php'; 


// Simple HTML Purifier Alternative (basic sanitization)
function sanitizeHtml($html) {
    $allowed_tags = '<html><head><style><body><<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    return strip_tags($html, $allowed_tags);
}

// Collects field-level validation errors for the section being saved, keyed
// by input name so each one can render next to its own field.
$field_errors = [];

/** True when the value is a syntactically valid email address. */
function validEmail($value) {
    return filter_var(trim((string)$value), FILTER_VALIDATE_EMAIL) !== false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings'])) {
    // Validate before touching the database — a half-saved SMTP config
    // silently breaks every invoice email for the store.
    if (!validEmail($_POST['smtp_from_email'] ?? '')) {
        $field_errors['smtp_from_email'] = t('validation.email_invalid');
    }
    if (trim($_POST['smtp_host'] ?? '') === '') {
        $field_errors['smtp_host'] = t('validation.smtp_host_required');
    }
    $port = (int)($_POST['smtp_port'] ?? 0);
    if ($port < 1 || $port > 65535) {
        $field_errors['smtp_port'] = t('validation.smtp_port_invalid');
    }
    if (trim($_POST['smtp_user'] ?? '') === '') {
        $field_errors['smtp_user'] = t('validation.smtp_username_required');
    }
    if (trim($_POST['smtp_pass'] ?? '') === '') {
        $field_errors['smtp_pass'] = t('validation.smtp_password_required');
    }
    if (trim($_POST['email_subject'] ?? '') === '') {
        $field_errors['email_subject'] = t('validation.subject_required');
    } elseif (mb_strlen($_POST['email_subject']) > 255) {
        $field_errors['email_subject'] = t('validation.max_length', ['max' => 255]);
    }
    if (trim($_POST['email_body'] ?? '') === '') {
        $field_errors['email_body'] = t('validation.body_required');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings']) && empty($field_errors)) {
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
        $success_message = t('settings.saved_email');
    } else {
        $error_message = t('settings.nothing_to_update');
    }
    ?>
    <script>window.location.hash = '#email';</script>
    <?php
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_settings'])) {
    // Validation failed — keep the user on the Email tab with their input.
    ?>
    <script>window.location.hash = '#email';</script>
    <?php
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general_settings'])) {
    $auto_invoice_customer = isset($_POST['auto_invoice_customer']) ? 'Yes' : 'No';
    $auto_invoice_personal = isset($_POST['auto_invoice_personal']) ? 'Yes' : 'No';
    $email_invoice = trim($_POST['email_invoice'] ?? '');

    // An empty address is allowed (it just means "use the store email"),
    // but a non-empty one has to actually be an address.
    if ($email_invoice !== '' && !validEmail($email_invoice)) {
        $field_errors['email_invoice'] = t('validation.email_invalid');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general_settings']) && empty($field_errors)) {

    // Update database
    $affectedRows = DBHelper::execute(
        "UPDATE stores SET auto_invoice_customer = ?, auto_invoice_personal = ?, email_invoice = ? WHERE id = ?",
        "ssss",
        [$auto_invoice_customer, $auto_invoice_personal, $email_invoice, $shop_id]
    );

    if ($affectedRows) {
        $success_message = t('settings.saved_general');
    } else {
        $error_message = t('settings.nothing_to_update');
    }
    ?>
    <script>window.location.hash = '#general';</script>
    <?php
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general_settings'])) {
    ?>
    <script>window.location.hash = '#general';</script>
    <?php
}

// Language: saved on its own so a failed SMTP validation elsewhere on the
// page can never block the merchant from switching language.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_language_settings'])) {
    $requested = $_POST['app_locale'] ?? '';
    $saved = i18n_save_locale($shop_id, $requested);

    if ($saved !== null) {
        // Re-boot so the rest of THIS response already renders in the new
        // language — the switch feels instant instead of one page behind.
        i18n_boot(null, $saved);
        $success_message = t('settings.saved_language');
    } else {
        $error_message = t('validation.language_invalid');
    }
    ?>
    <script>window.location.hash = '#language';</script>
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
        $success_message = t('settings.saved_invoice');
    } else {
        $error_message = t('settings.nothing_to_update');
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

    $LOGO_MAX_BYTES = 2 * 1024 * 1024;   // matches the 2MB stated in the help text

    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_upload'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        // Each failure path now reports why, instead of silently keeping the
        // old logo and claiming success.
        if (!in_array($file_ext, $allowed_ext)) {
            $field_errors['logo_upload'] = t('validation.logo_type_invalid');
        } elseif ($file['size'] > $LOGO_MAX_BYTES) {
            $field_errors['logo_upload'] = t('validation.logo_too_large');
        } else {
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
            } else {
                $field_errors['logo_upload'] = t('validation.logo_upload_failed');
            }
        }
    } elseif (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_INI_SIZE) {
        $field_errors['logo_upload'] = t('validation.logo_too_large');
    } elseif (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        $field_errors['logo_upload'] = t('validation.logo_upload_failed');
    }

    if (empty($field_errors)) {
        // Update database
        $affectedRows = DBHelper::execute(
            "UPDATE stores SET logo_url = ? WHERE id = ?",
            "ss",
            [$logo_url, $shop_id]
        );

        if ($affectedRows) {
            $success_message = t('settings.saved_logo');
        } else {
            $error_message = t('settings.nothing_to_update');
        }
    }
    ?>
    <script>window.location.hash = '#logo';</script>
    <?php
}

include 'nav.php'; 

// Default email template

$default_subject = i18n_default_email_subject();
$default_body = i18n_default_email_body();

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
        <h2><?= e('settings.title') ?></h2>

        <!-- Tab Navigation -->
        <div class="settings-tabs">
            <a href="#general" class="settings-tab active"><i class="icon-gear"></i> <?= e('settings.tab_general') ?></a>
            <a href="#email" class="settings-tab"><i class="icon-email"></i> <?= e('settings.tab_email') ?></a>
            <a href="#invoice" class="settings-tab"><i class="icon-invoice"></i> <?= e('settings.tab_invoice') ?></a>
            <a href="#logo" class="settings-tab"><i class="icon-logo"></i> <?= e('settings.tab_logo') ?></a>
            <a href="#language" class="settings-tab"><i class="icon-language"></i> <?= e('settings.tab_language') ?></a>
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
                <h3><?= e('settings.general_title') ?></h3>
                <form method="POST" class="general-settings-form">
                <input type="hidden" name="save_general_settings" value="1">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="auto_invoice_customer" <?= $settings['auto_invoice_customer'] === 'Yes' ? 'checked' : '' ?>>
                            <?= e('settings.auto_invoice_customer') ?>
                        </label>
                        <p class="description"><?= e('settings.auto_invoice_customer_help') ?></p>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="auto_invoice_personal"  <?= $settings['auto_invoice_personal'] === 'Yes' ? 'checked' : '' ?>>
                            <?= e('settings.auto_invoice_personal') ?>
                        </label>
                        <p class="description"><?= e('settings.auto_invoice_personal_help') ?></p>
                        <input type="email" name="email_invoice" value="<?= htmlspecialchars($settings['email_invoice']) ?>" placeholder="<?= e('settings.email_invoice_placeholder') ?>" class="form-input">
                        <?php if (isset($field_errors['email_invoice'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['email_invoice']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($currentPlan['price'] == 0.00): ?>
                    <p class="description">
                        <?= e('settings.free_plan_notice', [
                            'plan'   => $currentPlan['plan_name'],
                            'orders' => fmt_number((int)$currentPlan['order_limit']),
                            'emails' => fmt_number((int)$currentPlan['email_limit']),
                            'used'   => fmt_number((int)$currentPlan['order_used']),
                        ]) ?>
                        <a href="change-plan?shop=<?= htmlspecialchars($shop) ?>"><?= e('settings.free_plan_notice_link') ?></a>
                        <?= e('settings.free_plan_notice_suffix') ?>
                    </p>
                    <?php endif; ?>
                    <button type="submit" class="btn-save"><?= e('settings.save_general') ?></button>
                </form>
            </section>
            
            <!-- Email Settings -->
            <section id="email" class="settings-section">
                <h3><?= e('settings.email_title') ?></h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="save_email_settings" value="1">
                    <div class="form-group">
                        <label><?= e('settings.from_email') ?></label>
                        <input type="email" name="smtp_from_email" class="form-input" value="<?= htmlspecialchars($smtp_settings['from_email']) ?>" placeholder="<?= e('settings.from_email_placeholder') ?>" required>
                        <p class="description"><?= e('settings.from_email_help') ?></p>
                        <?php if (isset($field_errors['smtp_from_email'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['smtp_from_email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label><?= e('settings.display_name') ?></label>
                        <input type="text" name="smtp_displayname" class="form-input"  value="<?= htmlspecialchars($smtp_settings['displayname']) ?>"  placeholder="<?= e('settings.display_name_placeholder') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= e('settings.smtp_host') ?></label>
                        <input type="text" name="smtp_host" class="form-input"  value="<?= htmlspecialchars($smtp_settings['host']) ?>"  placeholder="<?= e('settings.smtp_host_placeholder') ?>" required>
                        <?php if (isset($field_errors['smtp_host'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['smtp_host']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><?= e('settings.smtp_port') ?></label>
                        <input type="number" name="smtp_port" class="form-input" value="<?= htmlspecialchars($smtp_settings['port']) ?>"  placeholder="<?= e('settings.smtp_port_placeholder') ?>" required>
                        <?php if (isset($field_errors['smtp_port'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['smtp_port']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><?= e('settings.smtp_username') ?></label>
                        <input type="text" name="smtp_user" class="form-input" value="<?= htmlspecialchars($smtp_settings['username']) ?>" placeholder="<?= e('settings.smtp_username_placeholder') ?>" required>
                        <?php if (isset($field_errors['smtp_user'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['smtp_user']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><?= e('settings.smtp_password') ?></label>
                        <input type="password" name="smtp_pass" class="form-input"  value="<?= htmlspecialchars($smtp_settings['password']) ?>" placeholder="••••••••" required>
                        <?php if (isset($field_errors['smtp_pass'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['smtp_pass']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><?= e('settings.email_subject') ?></label>
                        <input type="text" name="email_subject" class="form-input" value="<?= htmlspecialchars($smtp_settings['subject']) ?>" placeholder="<?= e('settings.email_subject_placeholder') ?>" required>
                        <p class="description"><?= e('settings.email_variables_help') ?></p>
                        <?php if (isset($field_errors['email_subject'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['email_subject']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><?= e('settings.email_body') ?></label>
                        <textarea name="email_body" class="form-input" rows="12" required><?= htmlspecialchars($smtp_settings['body']) ?></textarea>
                        <p class="description"><?= e('settings.email_variables_help') ?></p>
                        <?php if (isset($field_errors['email_body'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['email_body']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php
                    if($currentPlan['price']>'0'){ ?>
                    <button type="submit" class="btn-save"><?= e('settings.save_email') ?></button>
                    <?php  }else {?>
                    <p><?= e('settings.email_paid_only') ?></p>
                    <?php   } ?>

                </form>
            </section>
            
            <!-- Invoice Settings -->
            <section id="invoice" class="settings-section">
                <h3><?= e('settings.invoice_title') ?></h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="save_invoice_settings" value="1">
                    <div class="template-selector">
                        <h4><?= e('settings.choose_template') ?></h4>
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
                        <button type="submit" class="btn-save"><?= e('settings.save_template') ?></button>
                    </div>
                </form>
            </section>

            <!-- Logo Settings -->
            <section id="logo" class="settings-section">
                <h3><?= e('settings.logo_title') ?></h3>
                <form method="POST" class="settings-form" enctype="multipart/form-data">
                    <input type="hidden" name="save_logo_settings" value="1">

                    <div class="form-group">
                        <label><?= e('settings.current_logo') ?></label>
                        <?php if (!empty($row['logo_url'])): ?>
                            <div class="current-logo">
                                <img src="<?= htmlspecialchars($row['logo_url']) ?>" alt="<?= e('settings.current_logo_alt') ?>" style="max-height: 100px; margin: 10px 0;">
                            </div>
                        <?php else: ?>
                            <p><?= e('settings.no_logo') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><?= e('settings.upload_logo') ?></label>
                        <p class="description"><?= e('settings.upload_logo_help') ?></p>
                        <input type="file" name="logo_upload" accept="image/*" class="form-input">
                        <p class="description"><?= e('settings.upload_formats_help') ?></p>
                        <?php if (isset($field_errors['logo_upload'])): ?>
                            <p class="field-error"><?= htmlspecialchars($field_errors['logo_upload']) ?></p>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-save"><?= e('settings.save_logo') ?></button>
                </form>
            </section>

            <!-- Language -->
            <section id="language" class="settings-section">
                <h3><?= e('settings.language_title') ?></h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="save_language_settings" value="1">

                    <div class="form-group">
                        <label for="app_locale"><?= e('settings.language_label') ?></label>
                        <p class="description"><?= e('settings.language_help') ?></p>
                        <select name="app_locale" id="app_locale" class="form-input">
                            <?php foreach (i18n_locales() as $code => $meta): ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= $code === i18n_locale() ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($meta['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?= e('settings.language_auto_note') ?></p>
                    </div>

                    <button type="submit" class="btn-save"><?= e('settings.save_language') ?></button>
                </form>
            </section>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>