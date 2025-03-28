<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWB Auto PDF Invoices</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>SWB Auto PDF</h2>
            </div>
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="#"><i class="icon-dashboard"></i> Dashboard</a>
                </li>
                <li>
                    <a href="#"><i class="icon-analytics"></i> Website Analytics</a>
                </li>
                <li class="has-submenu">
                    <a href="#"><i class="icon-apps"></i> Apps <i class="icon-chevron"></i></a>
                    <ul class="submenu">
                        <li><a href="#">PDF Generator</a></li>
                        <li><a href="#">Email Notifications</a></li>
                    </ul>
                </li>
                <li class="has-submenu">
                    <a href="#"><i class="icon-settings"></i> Settings <i class="icon-chevron"></i></a>
                    <ul class="submenu">
                        <li><a href="#">Application Settings</a></li>
                        <li><a href="#">Email Settings</a></li>
                        <li><a href="#">Invoice Settings</a></li>
                    </ul>
                </li>
                <li>
                    <a href="#"><i class="icon-invoice"></i> Invoices</a>
                </li>
                <li>
                    <a href="#"><i class="icon-email"></i> Email</a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <header class="main-header">
                <h1>SWB Auto PDF Invoices</h1>
                <div class="user-profile">
                    <span>Admin</span>
                    <div class="avatar">A</div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Stats Cards -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-title">Conversion Rate</div>
                        <div class="stat-value">28.5%</div>
                        <div class="stat-trend up">↑ 2.5% from last month</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Total Sales This Month</div>
                        <div class="stat-value">$28,450</div>
                        <div class="stat-trend up">↑ $4,210 from last month</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Sales Overview</div>
                        <div class="stat-value">$42.5k</div>
                        <div class="stat-trend down">↓ $1,150 from last month</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Visits</div>
                        <div class="stat-value">12,749</div>
                        <div class="stat-trend up">↑ 25.5% from last month</div>
                    </div>
                </div>

                <!-- Subscription Package -->
                <div class="package-card">
                    <h3>Subscription Package</h3>
                    <div class="package-details">
                        <div class="package-info">
                            <div class="package-name">Basic Plan</div>
                            <div class="package-price">$9.99/month</div>
                        </div>
                        <div class="package-stats">
                            <div class="package-stat">
                                <span>Billing Cycle</span>
                                <strong>1 Month</strong>
                            </div>
                            <div class="package-stat">
                                <span>Next Billing Date</span>
                                <strong>2025-04-28</strong>
                            </div>
                            <div class="package-stat">
                                <span>Invoices Allowed</span>
                                <strong>300/month</strong>
                            </div>
                        </div>
                        <div class="package-usage">
                            <div class="usage-info">
                                <span>Used: 100</span>
                                <span>Remaining: 200</span>
                            </div>
                            <div class="usage-bar">
                                <div class="usage-progress" style="width: 33.33%"></div>
                            </div>
                        </div>
                        <button class="btn-upgrade">Upgrade Plan</button>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="orders-card">
                    <h3>Recent Orders</h3>
                    <table id="ordersTable" class="display">
                        <thead>
                            <tr>
                                <th>ORDER ID</th>
                                <th>DATE</th>
                                <th>CUSTOMER</th>
                                <th>TOTAL PRICE</th>
                                <th>INVOICE</th>
                                <th>EMAIL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1006</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$884.94</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generatepdf?shop_id=1&order_id6264994267436" target="_blank">View</a></td>
                            </tr>
                            <tr>
                                <td>1007</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$10.00</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generatepdf?shop_id=1&order_id6265004294444" target="_blank">View</a></td>
                            </tr>
                            <tr>
                                <td>1008</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$29.44</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generatepdf?shop_id=1&order_id6265006293292" target="_blank">View</a></td>
                            </tr>
                            <tr>
                                <td>1009</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$1,685.42</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generatepdf?shop_id=1&order_id6265017794860" target="_blank">View</a></td>
                            </tr>
                            <tr>
                                <td>1010</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$861.34</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generatepdf?shop_id=1&order_id6265029853484" target="_blank">View</a></td>
                            </tr>
                            <tr>
                                <td>1011</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$884.94</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generatepdf?shop_id=1&order_id6266005750060" target="_blank">View</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <footer class="main-footer">
                    <p>© 2025, made with ❤️ by <a href="https://www.silverwebbuzz.com" target="_blank">silverwebbuzz</a></p>
                </footer>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="script.js"></script>
</body>
</html>