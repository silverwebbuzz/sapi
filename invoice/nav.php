<nav class="horizontal-menu">
    <ul>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <a href="index.php"><i class="icon-dashboard"></i> Dashboard</a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '' ?>">
            <a href="analytics.php"><i class="icon-analytics"></i> Analytics</a>
        </li>
        <li class="menu-dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']), ['pdf.php', 'email-app.php']) ? 'active' : '' ?>">
            <a href="#"><i class="icon-apps"></i> Apps <i class="icon-chevron"></i></a>
            <ul class="dropdown-menu">
                <li><a href="pdf.php">PDF Generator</a></li>
                <li><a href="email-app.php">Email Notifications</a></li>
            </ul>
        </li>
        <li class="menu-dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']), ['app-settings.php', 'email-settings.php', 'invoice-settings.php']) ? 'active' : '' ?>">
            <a href="#"><i class="icon-settings"></i> Settings <i class="icon-chevron"></i></a>
            <ul class="dropdown-menu">
                <li><a href="app-settings.php">Application</a></li>
                <li><a href="email-settings.php">Email</a></li>
                <li><a href="invoice-settings.php">Invoice</a></li>
            </ul>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>">
            <a href="invoices.php"><i class="icon-invoice"></i> Invoices</a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'email.php' ? 'active' : '' ?>">
            <a href="email.php"><i class="icon-email"></i> Email</a>
        </li>
    </ul>
</nav>