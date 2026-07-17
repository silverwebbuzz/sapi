-- Tracks the "your plan limit is reached" email sent to the store owner, so it
-- goes out once per subscription row instead of once per incoming order.
-- Usage counters are never reset, so a new subscription row (upgrade/renewal)
-- is what re-arms these notices.

ALTER TABLE `store_subscriptions`
    ADD COLUMN `order_limit_notice_sent_at` DATETIME NULL DEFAULT NULL AFTER `email_used`,
    ADD COLUMN `email_limit_notice_sent_at` DATETIME NULL DEFAULT NULL AFTER `order_limit_notice_sent_at`;
