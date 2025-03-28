<?php include 'header.php'; ?>
<?php include 'nav.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h2>Orders List</h2>
        <div class="actions">
            <button class="btn-export">Export CSV</button>
            <button class="btn-filter">Filter Orders</button>
        </div>
    </div>

    <div class="orders-card">
        <table id="ordersTable" class="display">
            <thead>
                <tr>
                    <th>ORDER ID</th>
                    <th>DATE</th>
                    <th>CUSTOMER</th>
                    <th>AMOUNT</th>
                    <th>STATUS</th>
                    <th>INVOICE</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1001</td>
                    <td>2025-03-28 10:30:45</td>
                    <td>John Smith</td>
                    <td>$125.99</td>
                    <td><span class="status completed">Completed</span></td>
                    <td><a href="#" class="view-invoice">View</a></td>
                    <td>
                        <button class="btn-action resend">Resend</button>
                        <button class="btn-action download">Download</button>
                    </td>
                </tr>
                <!-- More rows would be loaded from database -->
            </tbody>
        </table>
    </div>
</main>

<?php include 'footer.php'; ?>