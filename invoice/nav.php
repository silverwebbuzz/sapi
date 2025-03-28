<nav class="horizontal-menu">
    <ul>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <a href="index.php"><i class="icon-dashboard"></i> Dashboard</a>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
            <a href="orders.php"><i class="icon-orders"></i> Orders</a>
        </li>
        <li class="menu-dropdown <?= in_array(basename($_SERVER['PHP_SELF']), ['settings.php']) ? 'active' : '' ?>">
            <a href="settings.php"><i class="icon-settings"></i> Settings <i class="icon-chevron"></i></a>
            <ul class="dropdown-menu">
                <li><a href="settings.php#general">General Settings</a></li>
                <li><a href="settings.php#email">Email Settings</a></li>
                <li><a href="settings.php#invoice">Invoice Settings</a></li>
            </ul>
        </li>
        <li class="<?= basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active' : '' ?>">
            <a href="billing.php"><i class="icon-billing"></i> Billing</a>
        </li>
    </ul>
</nav>