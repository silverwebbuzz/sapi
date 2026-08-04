-- Stores the merchant's explicit language choice from Settings > Language.
--
-- This is the highest-priority source in the locale detection chain:
--   1. stores.app_locale        (this column — the merchant picked it)
--   2. stores.primary_locale    (already populated from Shopify at install)
--   3. Accept-Language header   (browser)
--   4. 'en'
--
-- NULL is the normal state for existing stores and means "not chosen yet",
-- so they keep falling through to their Shopify shop locale. Nothing needs
-- backfilling.

ALTER TABLE `stores`
    ADD COLUMN `app_locale` VARCHAR(10) NULL DEFAULT NULL AFTER `primary_locale`;
