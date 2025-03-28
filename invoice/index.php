<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWB Auto PDF Invoices - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>SWB Auto PDF Invoices</h1>
            <div class="user-info">
                <span class="username">Admin</span>
                <i class="fas fa-user-circle"></i>
            </div>
        </header>

        <div class="dashboard-content">
            <div class="metrics-grid">
                <!-- Website Analytics Card -->
                <div class="metric-card">
                    <div class="card-header">
                        <h2>Website Analytics</h2>
                        <span class="badge">Live</span>
                    </div>
                    <div class="card-body">
                        <div class="conversion-rate">
                            <h3>Total Conversion Rate</h3>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: 26.5%"></div>
                                <span>26.5%</span>
                            </div>
                        </div>
                        <div class="spending-table">
                            <table>
                                <tr>
                                    <td>12h</td>
                                    <td>Spend</td>
                                    <td>18</td>
                                    <td>Order Size</td>
                                </tr>
                                <tr>
                                    <td>127</td>
                                    <td>Order</td>
                                    <td>2.3k</td>
                                    <td>Items</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Average Daily Sales Card -->
                <div class="metric-card highlight">
                    <div class="card-header">
                        <h2>Average Daily Sales</h2>
                    </div>
                    <div class="card-body">
                        <h3>Total Sales This Month</h3>
                        <div class="sales-amount">$28,450</div>
                    </div>
                </div>

                <!-- Sales Overview Card -->
                <div class="metric-card">
                    <div class="card-header">
                        <h2>Sales Overview</h2>
                    </div>
                    <div class="card-body">
                        <div class="sales-figure">$42.5k</div>
                        <div class="sales-metrics">
                            <div class="metric-item">
                                <span>Order</span>
                                <span>25.5%</span>
                            </div>
                            <div class="metric-item">
                                <span>Order</span>
                                <span>12.749</span>
                            </div>
                            <div class="metric-item">
                                <span>Value</span>
                                <span>VIS</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="orders-section">
                <div class="section-header">
                    <h2>Recent Orders</h2>
                    <div class="controls">
                        <div class="entries-select">
                            <span>10 entries per page</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search...">
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="orders-table">
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
                                <td><a href="https://sapi.silverwebbuzz.com/generated?rhoo_id=1&order_id6264994267436" class="email-link">Send Invoice</a></td>
                            </tr>
                            <tr>
                                <td>1007</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$10.00</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generated?rhoo_id=1&order_id6265004294444" class="email-link">Send Invoice</a></td>
                            </tr>
                            <tr>
                                <td>1008</td>
                                <td>2025-03-27 13:55:50</td>
                                <td>Bhavik Koradiya</td>
                                <td>$29.44</td>
                                <td><span class="status pending">Pending</span></td>
                                <td><a href="https://sapi.silverwebbuzz.com/generated?rhoo_id=1&order_id6265006293292" class="email-link">Send Invoice</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <div class="pagination">
                        <button class="page-btn active">1</button>
                        <button class="page-btn">2</button>
                        <button class="page-btn">3</button>
                        <button class="page-btn next">Next <i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>