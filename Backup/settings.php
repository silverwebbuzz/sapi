<!DOCTYPE html>
<html>
<head>
    <title>Invoice Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/css/bootstrap-colorpicker.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Invoice Settings</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <ul class="nav nav-tabs" id="settingsTabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#invoice">Invoice Template</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#email">Email Settings</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#smtp">SMTP Configuration</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#advanced">Advanced</a></li>
            </ul>

            <div class="tab-content mt-3">
                <!-- Invoice Template Tab -->
                <div class="tab-pane active" id="invoice">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label>Invoice Logo</label>
                                <input type="file" name="invoice_logo" class="form-control">
                                <?php if (!empty($settings['invoice']['logo'])): ?>
                                    <img src="<?= $settings['invoice']['logo'] ?>" class="img-thumbnail mt-2" style="max-height: 100px">
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label>Color Scheme</label>
                                <input type="text" name="color_scheme" class="form-control colorpicker" 
                                    value="<?= $settings['invoice']['color_scheme'] ?? '#2c3e50' ?>">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label>Custom Header</label>
                                <textarea name="custom_header" class="form-control" rows="3"><?= $settings['invoice']['custom_fields']['header'] ?? '' ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label>Custom Footer</label>
                                <textarea name="custom_footer" class="form-control" rows="3"><?= $settings['invoice']['custom_fields']['footer'] ?? '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Settings Tab -->
                <div class="tab-pane" id="email">
                    <div class="mb-3">
                        <label>Email Subject</label>
                        <input type="text" name="email_subject" class="form-control" 
                            value="<?= $settings['email']['subject'] ?? 'Invoice for Order {{order_number}}' ?>">
                    </div>
                    <div class="mb-3">
                        <label>Email Body</label>
                        <textarea name="email_body" class="form-control" rows="6"><?= $settings['email']['body'] ?? "Dear {{customer_name}},\n\nPlease find attached your invoice...\n" ?></textarea>
                        <small class="text-muted">Available variables: {{order_number}}, {{customer_name}}, {{invoice_amount}}, {{due_date}}</small>
                    </div>
                </div>

                <!-- SMTP Configuration Tab -->
                <div class="tab-pane" id="smtp">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" 
                                    value="<?= $settings['smtp']['host'] ?? '' ?>">
                            </div>
                            <div class="mb-3">
                                <label>Port</label>
                                <input type="number" name="smtp_port" class="form-control" 
                                    value="<?= $settings['smtp']['port'] ?? 587 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Username</label>
                                <input type="text" name="smtp_user" class="form-control" 
                                    value="<?= $settings['smtp']['username'] ?? '' ?>">
                            </div>
                            <div class="mb-3">
                                <label>Password</label>
                                <input type="password" name="smtp_pass" class="form-control" 
                                    placeholder="Leave blank to keep current">
                            </div>
                            <div class="mb-3">
                                <label>Encryption</label>
                                <select name="smtp_encryption" class="form-control">
                                    <option value="tls" <?= ($settings['smtp']['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= ($settings['smtp']['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Settings Tab -->
                <div class="tab-pane" id="advanced">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label>Invoice Number Prefix</label>
                                <input type="text" name="invoice_prefix" class="form-control" 
                                    value="<?= $settings['advanced']['invoice_prefix'] ?? 'INV-' ?>">
                            </div>
                            <div class="mb-3">
                                <label>Next Invoice Number</label>
                                <input type="number" name="next_number" class="form-control" 
                                    value="<?= $settings['advanced']['next_number'] ?? 1001 ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label>Due Date Days</label>
                                <input type="number" name="due_days" class="form-control" 
                                    value="<?= $settings['advanced']['due_days'] ?? 7 ?>">
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="auto_archive" class="form-check-input" 
                                    <?= ($settings['advanced']['auto_archive'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label">Auto-archive sent invoices</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">Save Settings</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.4.0/js/bootstrap-colorpicker.min.js"></script>
    <script>
        $(document).ready(function(){
            $('.colorpicker').colorpicker();
        });
    </script>
</body>
</html>