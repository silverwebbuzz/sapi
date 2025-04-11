<?php include 'header.php';
include 'nav.php'; 

//Fetch Plans
$plans = DBHelper::select("SELECT * FROM `plans` where price != '0.00'  ORDER BY id","",[]);
?>

<main class="main-content">
    <div class="billing-container">
        <h2>Billing & Subscription</h2>

        <div class="pricing-container">
            <h1>Welcome to SWB Auto PDF Invoices</h1>
            <h2>Choose the right plan for your store</h2>
            <p>All plans come with a 7-day free trial.</p>
            
            <div class="pricing-grid">
                <?php foreach ($plans as $plan) : ?>
                    <div class="pricing-card">
                        <h3><?= htmlspecialchars($plan['name']) ?></h3>
                        <p class="price">$<?= number_format($plan['price'], 2) ?>/month</p>
                        <p class="orders"><?= $plan['order_limit'] ?> Orders per month</p>
                        <p class="description"><?= htmlspecialchars($plan['description']) ?></p>
                        <form action="subscribe.php" method="GET">
                            <input type="hidden" name="shop_id" value="<?= $store['id'] ?>">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <button type="submit">Start Free Trial</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Current Plan -->
        <div class="current-plan">
            <h3>Your Current Plan</h3>
            <div class="plan-card active">
                <div class="plan-header">
                    <h4>Professional</h4>
                    <div class="plan-price">$29.99<span>/month</span></div>
                </div>
                <ul class="plan-features">
                    <li>Unlimited Invoices</li>
                    <li>500 Email Notifications</li>
                    <li>Advanced Analytics</li>
                    <li>Priority Support</li>
                </ul>
                <div class="plan-footer">
                    <div class="renewal-date">Renews on: <strong>April 28, 2025</strong></div>
                    <button class="btn-cancel">Cancel Subscription</button>
                </div>
            </div>
        </div>
        
        <!-- Available Plans -->
        <div class="available-plans">
            <h3>Available Plans</h3>
            <div class="plans-grid">
                <div class="plan-card">
                    <div class="plan-header">
                        <h4>Basic</h4>
                        <div class="plan-price">$9.99<span>/month</span></div>
                    </div>
                    <ul class="plan-features">
                        <li>100 Invoices/month</li>
                        <li>100 Email Notifications</li>
                        <li>Basic Analytics</li>
                        <li>Email Support</li>
                    </ul>
                    <button class="btn-upgrade">Upgrade</button>
                </div>
                
                <div class="plan-card recommended">
                    <div class="recommended-badge">Recommended</div>
                    <div class="plan-header">
                        <h4>Professional</h4>
                        <div class="plan-price">$29.99<span>/month</span></div>
                    </div>
                    <ul class="plan-features">
                        <li>Unlimited Invoices</li>
                        <li>500 Email Notifications</li>
                        <li>Advanced Analytics</li>
                        <li>Priority Support</li>
                    </ul>
                    <button class="btn-upgrade current">Current Plan</button>
                </div>
                
                <div class="plan-card">
                    <div class="plan-header">
                        <h4>Enterprise</h4>
                        <div class="plan-price">$99.99<span>/month</span></div>
                    </div>
                    <ul class="plan-features">
                        <li>Unlimited Invoices</li>
                        <li>Unlimited Notifications</li>
                        <li>Advanced Analytics</li>
                        <li>24/7 Support</li>
                        <li>API Access</li>
                    </ul>
                    <button class="btn-upgrade">Upgrade</button>
                </div>
            </div>
        </div>
        
        <!-- Billing History -->
        <div class="billing-history">
            <h3>Billing History</h3>
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Mar 28, 2025</td>
                        <td>Professional Plan Subscription</td>
                        <td>$29.99</td>
                        <td><span class="status paid">Paid</span></td>
                        <td><a href="#" class="view-invoice">Download</a></td>
                    </tr>
                    <!-- More rows would be loaded from database -->
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>