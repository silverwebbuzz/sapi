<?php include 'header.php'; ?>
<?php include 'nav.php'; ?>

<main class="main-content">
    <div class="settings-container">
        <h2>Settings</h2>
        
        <!-- General Settings -->
        <section id="general" class="settings-section">
            <h3><i class="icon-gear"></i> General Settings</h3>
            <div class="settings-form">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="auto_invoice" checked> 
                        Automatic invoices to customers
                    </label>
                    <p class="description">Enable/disable sending invoices to customers automatically</p>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="auto_copy" checked> 
                        Automatic personal copy
                    </label>
                    <p class="description">Receive a copy of every invoice automatically</p>
                    <input type="email" name="copy_email" placeholder="your-email@example.com" class="form-input">
                </div>
                
                <button class="btn-save">Save General Settings</button>
            </div>
        </section>
        
        <!-- Email Settings -->
        <section id="email" class="settings-section">
            <h3><i class="icon-email"></i> Email Settings</h3>
            <div class="settings-form">
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-input" placeholder="smtp.example.com">
                </div>
                
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" name="smtp_port" class="form-input" placeholder="587">
                </div>
                
                <div class="form-group">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_user" class="form-input" placeholder="your-email@example.com">
                </div>
                
                <div class="form-group">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-input" placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label>Email Format</label>
                    <select name="email_format" class="form-input">
                        <option value="html">HTML</option>
                        <option value="plain">Plain Text</option>
                    </select>
                </div>
                
                <button class="btn-save">Save Email Settings</button>
            </div>
        </section>
        
        <!-- Invoice Settings -->
        <section id="invoice" class="settings-section">
            <h3><i class="icon-invoice"></i> Invoice Settings</h3>
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
</main>

<?php include 'footer.php'; ?>