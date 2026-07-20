-- Anchors the start of the current usage window so plan quotas (e.g. Lifetime
-- Free = 20 invoices / 20 emails) reset per billing period instead of
-- accumulating for the life of the subscription row.
--
-- The reset is applied lazily in PHP (applyMonthlyUsageReset) on read/use, so
-- it does not depend on Shopify renewal webhooks and covers the free plan too.
-- Existing rows start with NULL and fall back to `activated_on` on first read.

ALTER TABLE `store_subscriptions`
    ADD COLUMN `usage_period_start` DATETIME NULL DEFAULT NULL AFTER `email_limit_notice_sent_at`;
