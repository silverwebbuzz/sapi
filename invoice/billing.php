<?php include 'header.php';
include 'nav.php'; 

//Fetch Plans
$plans = DBHelper::select("SELECT * FROM `plans` where price != '0.00'  ORDER BY id","",[]);
?>

<main class="main-content">
    <div class="billing-container">
        <h2>Subscription Plans</h2>
        <!-- Current Plan -->
        <div class="current-plan">
            <h3>Your Current Plan</h3>
            <div class="plan-card active">
                <div class="plan-header">
                    <h4><?= htmlspecialchars($currentPlan['plan_name'])?></h4>
                    <div class="plan-price">$<?= htmlspecialchars($currentPlan['price'])?><span>/month</span></div>
                    <div><?= htmlspecialchars($currentPlan['description']); ?></div>
                </div>
                <ul class="plan-features">
                    <li>Invoices : <?= htmlspecialchars($currentPlan['plan_order_limit'])?>/month</li>
                    <li>Emails Notifications with Invoice : <?= htmlspecialchars($currentPlan['email_limit'])?>/month </li>
                    <li>Auto Create and Send Invoices</li>
                    <li>Personalized Setup</li>
                    <li>Invoice Templates Selection</li>
                    <li>Email Template Change</li>
                    <li>Email Support</li>
                </ul>
                <div class="plan-footer">
                    <div class="renewal-date">Renews on: <strong><?= htmlspecialchars($currentPlan['cancelled_on'])?></strong></div>
                    <button class="btn-cancel">Cancel Subscription</button>
                </div>
            </div>
        </div>
        
        <!-- Available Plans -->
        <div class="available-plans">
            <h3>Available Plans</h3>
            <div class="plans-grid">

            <?php foreach ($plans as $plan) : ?>
                <div class="plan-card <?php if($plan['id']=='2') echo 'recommended'; ?>">
                <?php if($plan['id']=='2') echo '<div class="recommended-badge">Recommended</div>'; ?>
                    <div class="plan-header">
                        <h4><?= htmlspecialchars($plan['name']) ?></h4>
                        <div class="plan-price">$<?= number_format($plan['price'], 2) ?><span>/month</span></div>
                    </div>
                    
                    <ul class="plan-features">
                        <li><?= $plan['order_limit'] ?> Invoices/month</li>
                        <li><?= $plan['email_limit'] ?> Email Notifications</li>
                        <li>Auto Create and Send Invoices</li>
                        <li>Personalized Setup</li>
                        <li>Invoice Templates Selection</li>
                        <li>Email Template Change</li>
                        <li>Email Support</li>
                    </ul>
                    <input type="hidden" name="shop_id" value="<?= $shop_id ?>">
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    <button class="btn-upgrade <?php if($plan['id']=='2') echo 'current';?>">Upgrade</button>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        
    </div>
</main>

<?php include 'footer.php'; ?>