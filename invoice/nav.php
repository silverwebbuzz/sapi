<nav class="horizontal-menu">
    <ul>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <a href="index.php"><i class="icon-dashboard"></i> Dashboard</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'order.php' ? 'active' : '' ?>">
            <a href="order.php"><i class="icon-orders"></i>Shopify Orders</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active' : '' ?>">
            <a href="billing.php"><i class="icon-billing"></i> Subscription Plans</a>
        </li>
        <li class="menu-dropdown <?= in_array(basename($_SERVER['PHP_SELF']), ['settings.php']) ? 'active' : '' ?>">
            <a href="settings.php"><i class="icon-settings"></i> Settings <i class="icon-chevron"></i></a>
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
$sql_settings = "SELECT store_name,smtp_settings,auto_invoice_customer, auto_invoice_personal, email_invoice, email, invoice_templates_id FROM stores WHERE id = ?";
$row = DBHelper::selectOne($sql_settings,"s", [$shop_id]);

if($currentPlan['price']>0 && $row['smtp_settings']==''): // You can set this based on your SMTP check logic ?>
<div id="smtp-warning" class="warning-box">
    <span style="font-size:18px">⚠</span> Please <a href="billing">upgrade</a> you plan and set your <a href="settings.php#email">SMTP settings</a> to receive invoice emails from your defined Email Address.
</div>
<?php endif; ?>