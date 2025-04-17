<nav class="horizontal-menu">
    <ul>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <a href="index">Dashboard</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'order.php' ? 'active' : '' ?>">
            <a href="order">Shopify Orders</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active' : '' ?>">
            <a href="change-plan"><i class="icon-billing"></i>Change Plans</a>
        </li>
        <li class="menu-dropdown <?= in_array(basename($_SERVER['PHP_SELF']), ['settings.php']) ? 'active' : '' ?>">
            <a href="settings">Settings</a>
            <ul class="dropdown-menu">
                <li><a href="settings.php#general">General Settings</a></li>
                <li><a href="settings.php#email">Email Settings</a></li>
                <li><a href="settings.php#invoice">Invoice Settings</a></li>
            </ul>
        </li>
    </ul>
</nav>

<?php 
// Fetch existing settings
$sql_settings = "SELECT access_token, store_name,smtp_settings,auto_invoice_customer, auto_invoice_personal, email_invoice, email, invoice_templates_id FROM stores WHERE id = ?";
$row = DBHelper::selectOne($sql_settings,"s", [$shop_id]);

/*$webhooks = listWebhooks($shop, $row['access_token']);
foreach ($webhooks as $hook) {
    echo "Topic: {$hook['topic']}, URL: {$hook['address']}<br>";
}*/
/*$charge_id='34950971692';
$chargedetails = getChargeDetails($shop, $row['access_token'], $charge_id);
print_r($chargedetails);

Array ( [recurring_application_charge] => Array ( [id] => 34950971692 [name] => Premium [price] => 529.99 [billing_on] => 2026-04-23 [status] => active [created_at] => 2025-04-16T02:07:00-07:00 [updated_at] => 2025-04-16T02:07:06-07:00 [activated_on] => 2025-04-16 [return_url] => https://admin.shopify.com/store/silverwebbuzzapp/apps/swb-auto-pdf-invoices/ [test] => 1 [cancelled_on] => [trial_days] => 7 [trial_ends_on] => 2025-04-23 [api_client_id] => 227638050817 [decorated_return_url] => https://admin.shopify.com/store/silverwebbuzzapp/apps/swb-auto-pdf-invoices/?charge_id=34950971692 [currency] => USD ) )
*/
$message = '';
$gen_invoice_upgrade_plan_button = "";
$send_email_upgrade_plan_button = "";

if ($currentPlan['price'] == 0.00) {
    $message = "Please upgrade your plan and configure your SMTP settings to send emails from your own email address.";
    if (isset($currentPlan['order_limit'], $currentPlan['order_used'])) {
        if ($currentPlan['order_used'] >= $currentPlan['order_limit']) {
            $gen_invoice_upgrade_plan_button = '<a href="#" class="gen-invoice-btn" onclick="showMessageModal('.$message.') return false;" class="view-invoice">Upgrade To Generate Invoice</a>';
        }
    }

    // Check Email Limit
    if (isset($currentPlan['email_limit'], $currentPlan['email_used'])) {
        if ($currentPlan['email_used'] >= $currentPlan['email_limit']) {
            $send_email_upgrade_plan_button = '<a href="#" class="gen-invoice-btn" onclick="showMessageModal('.$message.') return false;" class="view-invoice">Upgrade To Send Email</a>';
        }
    }
} elseif ($currentPlan['price'] != 0.00) {
    // Check Order Limit
    if (isset($currentPlan['order_limit'], $currentPlan['order_used'])) {
        if ($currentPlan['order_used'] >= $currentPlan['order_limit']) {
            $message = "Please upgrade your plan to send more invoices.";
            $gen_invoice_upgrade_plan_button = '<a href="#" class="gen-invoice-btn" onclick="showMessageModal('.$message.') return false;" class="view-invoice">Upgrade To Generate Invoice</a>';
        }
    }

    // Check Email Limit
    if (isset($currentPlan['email_limit'], $currentPlan['email_used'])) {
        if ($currentPlan['email_used'] >= $currentPlan['email_limit']) {
            $message = "Please upgrade your plan to send more emails.";
            $send_email_upgrade_plan_button = '<a href="#" class="gen-invoice-btn" onclick="showMessageModal('.$message.') return false;" class="view-invoice">Upgrade To Send Email</a>';
        }
    }

    // Check SMTP Settings
    if (empty($row['smtp_settings'])) {
        $message .= "Please configure your SMTP settings to send emails from your own email address.";
    }
}

if(!empty($message)): // You can set this based on your SMTP check logic ?>
<div id="smtp-warning" class="warning-box">
    <span style="font-size:18px">⚠</span> <?=$message?>
</div>
<?php endif; ?>
<div id="messageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="background:#fff; width:400px; margin:100px auto; padding:20px; border-radius:8px; position:relative;">
        <p id="messageText" style="margin:0; font-size:16px;"></p>
        <button onclick="closeMessageModal()" style="margin-top:20px; float:right;">Close</button>
    </div>
</div>