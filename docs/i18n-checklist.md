# i18n verification checklist

Work through this before shipping the i18n release, and again whenever a
language or a screen is added. See `docs/i18n.md` for how the system works.

## Automated (run these first — they catch most regressions)

```bash
# Catalog health: key parity, placeholder drift, malformed JSON
php tools/check-translations.php
# → must end with "All locales healthy."

# Untranslated values as well (informational — 6 are intentional)
php tools/check-translations.php --strict

# Syntax
for f in invoice/*.php config/*.php index.php verify_token.php; do php -l "$f"; done
node --check js/script.js

# Extension catalogs regenerate cleanly
cd shopify-admin-extension && npm run sync-locales && npm run build
```

**Grep sweeps — each should return nothing:**

```bash
# Literal text between tags in PHP views
grep -nE '>[A-Za-z][A-Za-z ,\.\?!&;-]{4,}<' invoice/*.php \
  | grep -vE "e\('|t\('|t_html|htmlspecialchars|^\s*//"

# Literal strings reaching the UI from JS
grep -nE "(\.text|showMessage|confirm)\(\s*['\"\`][A-Za-z]" js/script.js

# English left in the PDF templates
grep -nE '>[A-Za-z][A-Za-z ]{3,}<' invoice/invoice_templates/html/*.html | grep -v '{{'
```

---

## Setup

- [ ] `invoice/sql/2026_08_03_add_app_locale.sql` applied to every environment
- [ ] `php -m | grep intl` shows `intl` on the production server
- [ ] `define('I18N_DEBUG', true)` set on staging, **absent** on production

## Language switcher

- [ ] Settings → Language lists all 19 languages in their own script
      (Deutsch, 日本語, العربية … — not "German", "Japanese", "Arabic")
- [ ] The dropdown pre-selects the language currently in effect
- [ ] Saving shows the success toast **already in the new language**
- [ ] Reload → the choice persists
- [ ] Log out, reopen the app from Shopify admin → still the chosen language
- [ ] With `app_locale` NULL, a shop whose Shopify locale is German opens in German
- [ ] With `app_locale` NULL and shop locale English, a `de-DE` browser opens in German
- [ ] An unsupported browser language (e.g. `th-TH`) falls back to English

## Screens — check each in English, one European language, Japanese and Arabic

- [ ] **Dashboard** — plan card, billing cycle, limits, usage counters, table headers, row actions
- [ ] **Shopify Orders** — title, subtitle, bulk toolbar, hint text, statuses, every action button
- [ ] **Bulk Download** — both tabs, banners, empty state, ZIP button
- [ ] **Packing Slips** — title, setup/upgrade banners, quota hint, actions
- [ ] **Settings → General / Email / Invoice / Logo / Language** — every label, placeholder, help text, button
- [ ] **Help** — headings, list items, bolded menu paths render as markup not escaped tags
- [ ] **Billing / Change plan** — plan features, renewal, redirect page
- [ ] Top nav, App Bridge nav menu, footer credit
- [ ] No raw keys visible anywhere (a stray `settings.title` on screen = missing key)

## Toasts, modals, validation

- [ ] Generate invoice → success and failure toasts translated
- [ ] Send email / Send to store owner → both outcomes translated
- [ ] A **failed** send shows a red (error) toast, not green
- [ ] Bulk generate → progress title, per-item status, cancel, completion summary
- [ ] Bulk confirm dialogs (cap and quota variants) translated with correct numbers
- [ ] DataTables chrome — search, "Show N entries", pagination, empty table
- [ ] Settings: invalid email, blank SMTP host, bad port, oversized/wrong-type logo
      each show a translated message under the right field
- [ ] Cancel-subscription and upgrade confirms translated

## PDF documents — generate one per locale group

- [ ] Invoice: title, Invoice No, Date, Merchant, Bill To, Ship To
- [ ] Invoice: Item, Description, Price, Qty, Total column headers
- [ ] Invoice: Subtotal, Discount, Shipping, Grand Total, Payment Info
- [ ] Invoice: footer "computer-generated" note
- [ ] Packing slip: title, Order/Order Date/Ship Date, Ship To, Bill To, Item, Qty,
      total-items line, "Picked & packed by", footer note
- [ ] **No literal `{{ L_… }}` visible anywhere in a rendered PDF**
- [ ] Money formats per locale (`1.234,50 €` in German, `€1,234.50` in English)
- [ ] Japanese yen shows no decimals
- [ ] Dates formatted per locale, not `d/m/Y` everywhere
- [ ] Tax names from Shopify (VAT, CGST, GST) are **not** translated — they're data
- [ ] Arabic/Hebrew invoice renders right-to-left with `dir="rtl"` on `<html>`
- [ ] CJK and Arabic glyphs render in the PDF rather than as boxes — the bundled
      DejaVu Sans has limited CJK coverage; see "Known gaps" below

## Emails

- [ ] A store with no custom SMTP gets the invoice email in the store's language
- [ ] Subject and body keep working `{invoice_number}`, `{customer_name}`,
      `{total_price}`, `{currency}`, `{created_at}` substitution
- [ ] A store that **already customised** its subject/body keeps its own text untouched
- [ ] `[Store Copy]` prefix translated
- [ ] Plan-limit notice email arrives in the merchant's language, links to upgrade

## RTL (Arabic and Hebrew)

- [ ] `<html dir="rtl">` present; layout mirrors
- [ ] `css/rtl.css` loads for ar/he and **not** for other languages
- [ ] Nav, tabs and table headers right-aligned
- [ ] Toast appears top-left; modal close button top-left
- [ ] Email, SMTP host and port inputs still read left-to-right
- [ ] Email-body textarea stays LTR (it holds HTML)
- [ ] Numbers and dates use Arabic-Indic digits where the locale expects them

## Admin order-details extension

- [ ] Opens on an order, button and body text translated
- [ ] Follows the Shopify admin user's language
- [ ] Error banners (no order / no shop domain) translated
- [ ] Falls back to English for a locale the app doesn't ship

## No regressions

- [ ] Invoice generation, email sending, bulk ZIP, packing slips all still work
- [ ] Date column still sorts correctly despite localised display (`data-order`)
- [ ] Free-plan gates and quota limits still enforced
- [ ] Install/OAuth flow completes
- [ ] Webhooks still 200 (they are machine-facing and intentionally untranslated)

---

## Known gaps — decide before release

1. **PDF fonts.** dompdf is configured with `DejaVu Sans`, which covers Latin,
   Cyrillic, Greek and Arabic/Hebrew but **not** CJK. Japanese, Korean and
   Chinese invoices will show missing glyphs until a CJK font (e.g. Noto Sans CJK)
   is registered with dompdf. The i18n layer is done; this is a font asset task.

2. **Arabic/Hebrew shaping in dompdf** is basic. Verify a real Arabic invoice
   visually before enabling those languages for merchants.

3. **Marketing/legal pages** (`home.php`, `faq.php`, `privacy.php`) are
   deliberately **not** translated — they sit outside the embedded app and the
   privacy text carries legal weight that machine translation shouldn't touch.

4. **Machine translations, unreviewed.** All 18 non-English catalogs were
   produced without a native reviewer. Before a wide launch, have a speaker check
   at least: `invoice.*` and `packing_slip.*` (they appear on customer-facing tax
   documents), `limit_email.*` and `invoice_email.*`, and the `validation.*` set.
