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
$smtp_settings_data = DBHelper::selectOne(
    "SELECT smtp_settings FROM stores WHERE `id` = ? ",
    "s", 
    [$shop_id]
);
if (!$smtp_settings_data['smtp_settings']): // You can set this based on your SMTP check logic ?>
<div id="smtp-warning" class="warning-box">
    <span style="font-size:18px">⚠</span> Please upgrade you plan and set your SMTP settings to receive invoice emails from your defined Email Address.
</div>
<?php endif; ?>