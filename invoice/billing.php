<?php include 'header.php';
include 'nav.php'; 

//Fetch Plans
$plans = DBHelper::select("SELECT * FROM `plans` where price != '0.00'  ORDER BY id","",[]);
?>

<main class="main-content">
    <div class="billing-container">
        <h2><?= e('billing.title') ?></h2>
        <!-- Current Plan -->
        <div class="current-plan">
            <h3><?= e('billing.current_plan') ?></h3>
            <div class="plan-card active">
                <div class="plan-header">
                    <h4><?= htmlspecialchars($currentPlan['plan_name'])?></h4>
                    <div class="plan-price"><?= htmlspecialchars(fmt_currency($currentPlan['price'], $currentPlan['currency'] ?? 'USD')) ?><span><?= e('common.per_month') ?></span></div>
                    <div><?= htmlspecialchars($currentPlan['description']); ?></div>
                </div>
                <ul class="plan-features">
                    <li><?= e('billing.invoices_per_month', ['count' => fmt_number((int)$currentPlan['plan_order_limit'])]) ?></li>
                    <li><?= e('billing.emails_per_month', ['count' => fmt_number((int)$currentPlan['email_limit'])]) ?></li>
                    <li><?= e('billing.feature_auto') ?></li>
                    <li><?= e('billing.feature_setup') ?></li>
                    <li><?= e('billing.feature_templates') ?></li>
                    <li><?= e('billing.feature_email_template') ?></li>
                    <li><?= e('billing.feature_support') ?></li>
                </ul>
                <div class="plan-footer">
                    <div class="renewal-date"><?= e('billing.renews_on') ?> <strong><?= htmlspecialchars(fmt_date($currentPlan['next_charge_date'])) ?></strong></div>
                    <a class="btn-cancel" href="cancel-charge.php?shop_id=<?= urlencode($shop_id) ?>&plan_id=<?= $currentPlan['plan_id'] ?>"><?= e('billing.cancel_plan') ?></a>
                </div>
            </div>
        </div>

        <!-- Available Plans -->
        <div class="available-plans">
            <h3><?= e('billing.available_plans') ?></h3>
            <div class="plans-grid">

            <?php foreach ($plans as $plan) : ?>
                <?php if ($plan['id'] != $currentPlan['plan_id']) { ?>
                <div class="plan-card <?php if($plan['id']=='2') echo 'recommended'; ?>">
                <?php if($plan['id']=='2') echo '<div class="recommended-badge">' . e('billing.recommended') . '</div>'; ?>
                    <div class="plan-header">
                        <h4><?= htmlspecialchars($plan['name']) ?></h4>
                        <div class="plan-price"><?= htmlspecialchars(fmt_currency($plan['price'], $plan['currency'] ?? 'USD')) ?><span><?= e('common.per_month') ?></span></div>
                        <div><?= htmlspecialchars($plan['description']); ?></div>
                    </div>

                    <ul class="plan-features">
                        <li><?= e('billing.plan_invoices', ['count' => fmt_number((int)$plan['order_limit'])]) ?></li>
                        <li><?= e('billing.plan_emails', ['count' => fmt_number((int)$plan['email_limit'])]) ?></li>
                        <li><?= e('billing.feature_auto') ?></li>
                        <li><?= e('billing.feature_setup') ?></li>
                        <li><?= e('billing.feature_templates') ?></li>
                        <li><?= e('billing.feature_email_template') ?></li>
                        <li><?= e('billing.feature_support') ?></li>
                    </ul>
                    <input type="hidden" name="shop_id" value="<?= $shop_id ?>">
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    <a class="btn-upgrade <?php if($plan['id']=='2') echo 'current';?>" href="create-charge.php?shop_id=<?= urlencode($shop_id) ?>&plan_id=<?= $plan['id'] ?>"><?= e('common.upgrade') ?></a>
                </div>
                <?php } ?>
            <?php endforeach; ?>
            </div>
        </div>
        
    </div>
</main>

<?php include 'footer.php'; ?>