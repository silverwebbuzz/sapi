<?php
/**
 * SWB Auto PDF Invoices — internationalisation core.
 *
 * Translations live in /locales/<locale>/common.json as FLAT, dot-notation
 * keys ("settings.title"). The format is deliberately i18next-compatible:
 * the same files can be consumed by i18next in the browser (keySeparator:
 * false) or imported into a future React port without re-translation.
 *
 * Only the active locale (plus the English fallback) is ever read from disk,
 * so adding languages costs nothing at runtime.
 *
 * Usage:
 *     require_once 'i18n.php';
 *     i18n_boot($store);                  // once per request, after $store is loaded
 *     echo e('nav.dashboard');            // HTML-escaped (use this in markup)
 *     echo t('orders.selected', ['count' => 3]);   // raw string
 */

// ---------------------------------------------------------------------------
// Supported locales
// ---------------------------------------------------------------------------

/**
 * The single source of truth for which languages the app ships.
 *
 * To add a language: create /locales/<code>/common.json and add one row here.
 * Nothing else in the codebase needs to change.
 *
 *   label  — endonym, shown in the switcher (users recognise their own language)
 *   dir    — 'ltr' | 'rtl'
 *   intl   — ICU locale used for date/number/currency formatting
 */
function i18n_locales() {
    static $locales = [
        'en'    => ['label' => 'English',    'dir' => 'ltr', 'intl' => 'en_US'],
        'de'    => ['label' => 'Deutsch',    'dir' => 'ltr', 'intl' => 'de_DE'],
        'fr'    => ['label' => 'Français',   'dir' => 'ltr', 'intl' => 'fr_FR'],
        'es'    => ['label' => 'Español',    'dir' => 'ltr', 'intl' => 'es_ES'],
        'it'    => ['label' => 'Italiano',   'dir' => 'ltr', 'intl' => 'it_IT'],
        'nl'    => ['label' => 'Nederlands', 'dir' => 'ltr', 'intl' => 'nl_NL'],
        'pt'    => ['label' => 'Português',  'dir' => 'ltr', 'intl' => 'pt_PT'],
        'pl'    => ['label' => 'Polski',     'dir' => 'ltr', 'intl' => 'pl_PL'],
        'cs'    => ['label' => 'Čeština',    'dir' => 'ltr', 'intl' => 'cs_CZ'],
        'da'    => ['label' => 'Dansk',      'dir' => 'ltr', 'intl' => 'da_DK'],
        'sv'    => ['label' => 'Svenska',    'dir' => 'ltr', 'intl' => 'sv_SE'],
        'nb'    => ['label' => 'Norsk',      'dir' => 'ltr', 'intl' => 'nb_NO'],
        'fi'    => ['label' => 'Suomi',      'dir' => 'ltr', 'intl' => 'fi_FI'],
        'ja'    => ['label' => '日本語',      'dir' => 'ltr', 'intl' => 'ja_JP'],
        'ko'    => ['label' => '한국어',      'dir' => 'ltr', 'intl' => 'ko_KR'],
        'zh-CN' => ['label' => '简体中文',    'dir' => 'ltr', 'intl' => 'zh_Hans_CN'],
        'zh-TW' => ['label' => '繁體中文',    'dir' => 'ltr', 'intl' => 'zh_Hant_TW'],
        'ar'    => ['label' => 'العربية',     'dir' => 'rtl', 'intl' => 'ar_SA'],
        'he'    => ['label' => 'עברית',       'dir' => 'rtl', 'intl' => 'he_IL'],
    ];
    return $locales;
}

define('I18N_FALLBACK_LOCALE', 'en');

/** Absolute path to the /locales directory (repo root, next to /invoice). */
function i18n_locales_path() {
    return dirname(__DIR__) . '/locales';
}

// ---------------------------------------------------------------------------
// Request state
// ---------------------------------------------------------------------------

/**
 * Get (and on first call, lazily default) the active locale for this request.
 * i18n_boot() normally sets this; the default keeps t() safe if a script
 * forgets to boot.
 */
function &i18n_state() {
    static $state = [
        'locale'  => I18N_FALLBACK_LOCALE,
        'missing' => [],
    ];
    return $state;
}

function i18n_locale() {
    $state = &i18n_state();
    return $state['locale'];
}

/** 'ltr' | 'rtl' for the active locale — feeds <html dir>. */
function i18n_dir($locale = null) {
    $locale = $locale ?: i18n_locale();
    $locales = i18n_locales();
    return $locales[$locale]['dir'] ?? 'ltr';
}

function i18n_is_rtl($locale = null) {
    return i18n_dir($locale) === 'rtl';
}

/** ICU locale for the Intl formatters. */
function i18n_intl_locale($locale = null) {
    $locale = $locale ?: i18n_locale();
    $locales = i18n_locales();
    return $locales[$locale]['intl'] ?? 'en_US';
}

// ---------------------------------------------------------------------------
// Locale negotiation
// ---------------------------------------------------------------------------

/**
 * Map an arbitrary locale tag onto one we actually ship.
 *
 * Handles the shapes Shopify and browsers really send: "de-DE" -> "de",
 * "pt-BR" -> "pt", "zh-Hans" / "zh" -> "zh-CN", "no" / "nn" -> "nb",
 * "iw" (legacy Hebrew) -> "he".
 *
 * Returns null when nothing sensible matches, so callers can fall through
 * to the next source in the detection chain.
 */
function i18n_normalize_locale($tag) {
    if (!is_string($tag) || $tag === '') {
        return null;
    }

    $tag = str_replace('_', '-', trim($tag));
    $supported = i18n_locales();

    // Exact match, case-insensitively ("zh-cn" -> "zh-CN").
    foreach (array_keys($supported) as $code) {
        if (strcasecmp($code, $tag) === 0) {
            return $code;
        }
    }

    $parts  = explode('-', $tag);
    $lang   = strtolower($parts[0]);
    $region = isset($parts[1]) ? strtolower($parts[1]) : '';

    // Chinese needs script/region to pick simplified vs traditional.
    if ($lang === 'zh') {
        if (in_array($region, ['tw', 'hk', 'mo', 'hant'], true)) {
            return 'zh-TW';
        }
        return 'zh-CN';
    }

    // Norwegian: Shopify sends "no"; Bokmål/Nynorsk both land on our "nb".
    if (in_array($lang, ['no', 'nb', 'nn'], true)) {
        return 'nb';
    }

    // Legacy ISO codes some browsers still emit.
    if ($lang === 'iw') { return 'he'; }
    if ($lang === 'in') { return 'id'; }   // not shipped — falls through below

    return isset($supported[$lang]) ? $lang : null;
}

/**
 * Pick the best locale from the browser's Accept-Language header, honouring
 * q-weights so "de;q=0.9, en;q=0.8" prefers German.
 */
function i18n_locale_from_browser($header = null) {
    $header = $header ?? ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if ($header === '') {
        return null;
    }

    $candidates = [];
    foreach (explode(',', $header) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }
        $bits = explode(';', $chunk);
        $tag  = trim($bits[0]);
        $q    = 1.0;
        foreach (array_slice($bits, 1) as $param) {
            if (stripos($param, 'q=') !== false) {
                $q = (float)substr(trim($param), 2);
            }
        }
        $candidates[] = ['tag' => $tag, 'q' => $q];
    }

    usort($candidates, function ($a, $b) {
        return $b['q'] <=> $a['q'];
    });

    foreach ($candidates as $candidate) {
        $locale = i18n_normalize_locale($candidate['tag']);
        if ($locale !== null) {
            return $locale;
        }
    }

    return null;
}

/**
 * Resolve the locale for this request, in the documented priority order:
 *
 *   1. the language the merchant picked in Settings (stores.app_locale)
 *   2. the Shopify shop locale (stores.primary_locale)
 *   3. the browser's Accept-Language
 *   4. English
 *
 * $store is the row from `stores`; pass null on endpoints that have none.
 */
function i18n_detect_locale($store = null) {
    if (is_array($store)) {
        // 1. Explicit merchant choice always wins.
        $chosen = i18n_normalize_locale($store['app_locale'] ?? null);
        if ($chosen !== null) {
            return $chosen;
        }
        // 2. Shop locale from Shopify (captured at install in callback.php).
        $shopLocale = i18n_normalize_locale($store['primary_locale'] ?? null);
        if ($shopLocale !== null) {
            return $shopLocale;
        }
    }

    // 3. Browser.
    $browser = i18n_locale_from_browser();
    if ($browser !== null) {
        return $browser;
    }

    // 4. Default.
    return I18N_FALLBACK_LOCALE;
}

/**
 * Initialise i18n for the current request. Safe to call more than once.
 *
 * Pass the `stores` row when you have one so the merchant's saved language
 * and shop locale are honoured; endpoints without a store still get browser
 * detection.
 */
function i18n_boot($store = null, $forceLocale = null) {
    $state = &i18n_state();

    $locale = $forceLocale !== null
        ? i18n_normalize_locale($forceLocale)
        : i18n_detect_locale($store);

    $state['locale'] = $locale ?: I18N_FALLBACK_LOCALE;

    return $state['locale'];
}

// ---------------------------------------------------------------------------
// Catalog loading (lazy, per-request memoised)
// ---------------------------------------------------------------------------

/**
 * Read one locale's JSON catalog off disk. Only the requested locale is
 * touched — no language is loaded until something asks for it.
 *
 * A malformed or missing file degrades to an empty catalog rather than
 * taking the page down; the English fallback then covers every key.
 */
function i18n_load_catalog($locale) {
    static $cache = [];

    if (isset($cache[$locale])) {
        return $cache[$locale];
    }

    $path = i18n_locales_path() . '/' . $locale . '/common.json';
    $catalog = [];

    if (is_readable($path)) {
        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $catalog = $decoded;
        } else {
            error_log('[i18n] Invalid JSON in ' . $path . ': ' . json_last_error_msg());
        }
    } elseif ($locale === I18N_FALLBACK_LOCALE) {
        // The English catalog is the safety net for every other language; if
        // it is gone, every string on the page silently becomes its own key.
        error_log('[i18n] Fallback catalog missing at ' . $path);
    }

    $cache[$locale] = $catalog;
    return $catalog;
}

/**
 * Look a key up in the active locale, then English, then give up.
 * Returns null when the key exists nowhere, so t() can report it.
 */
function i18n_lookup($key, $locale) {
    $catalog = i18n_load_catalog($locale);
    if (isset($catalog[$key]) && $catalog[$key] !== '') {
        return $catalog[$key];
    }

    if ($locale !== I18N_FALLBACK_LOCALE) {
        $fallback = i18n_load_catalog(I18N_FALLBACK_LOCALE);
        if (isset($fallback[$key]) && $fallback[$key] !== '') {
            return $fallback[$key];
        }
    }

    return null;
}

/**
 * Record a key that resolved nowhere.
 *
 * In development (I18N_DEBUG truthy) this writes to the error log so missing
 * strings surface while you work; in production it stays quiet but still
 * collects the keys, which i18n_missing_keys() can dump for tooling.
 */
function i18n_report_missing($key, $locale) {
    $state = &i18n_state();
    if (isset($state['missing'][$key])) {
        return;
    }
    $state['missing'][$key] = $locale;

    if (defined('I18N_DEBUG') && I18N_DEBUG) {
        error_log('[i18n] MISSING KEY "' . $key . '" (locale: ' . $locale . ')');
    }
}

/** Keys that fell through to no translation during this request. */
function i18n_missing_keys() {
    $state = &i18n_state();
    return $state['missing'];
}

// ---------------------------------------------------------------------------
// Translation
// ---------------------------------------------------------------------------

/**
 * Translate a key.
 *
 * Interpolation uses i18next's {{name}} syntax and tolerates the spaced
 * form ({{ name }}) that the invoice templates already use:
 *
 *     t('orders.bulk_hint', ['cap' => 50, 'remaining' => 12])
 *
 * Values are NOT escaped here — use e() in markup, or escape the individual
 * values before passing them in when the result is echoed raw.
 *
 * A missing key returns the key itself. That is deliberate: an untranslated
 * screen shows "settings.title" (obvious, reportable) rather than a blank.
 */
function t($key, array $params = [], $locale = null) {
    $locale = $locale ?: i18n_locale();

    $string = i18n_lookup($key, $locale);
    if ($string === null) {
        i18n_report_missing($key, $locale);
        return $key;
    }

    if (!empty($params)) {
        $string = i18n_interpolate($string, $params);
    }

    return $string;
}

/**
 * Translate and HTML-escape. This is the one to use inside markup.
 *
 * Placeholder values are escaped too, so t()-ed strings carrying user data
 * (store names, plan names) can't inject markup.
 */
function e($key, array $params = [], $locale = null) {
    $escaped = [];
    foreach ($params as $name => $value) {
        $escaped[$name] = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    // Escape the translation first, then substitute already-escaped values.
    // htmlspecialchars leaves {{...}} untouched, so the order is safe.
    $string = htmlspecialchars(t($key, [], $locale), ENT_QUOTES, 'UTF-8');
    return i18n_interpolate($string, $escaped);
}

/**
 * Translate a string that intentionally contains markup (help text with
 * <strong>, links built from placeholders). Placeholder VALUES are still
 * escaped; the translated string itself is trusted, because translations
 * are ours, not user input.
 */
function t_html($key, array $params = [], $locale = null) {
    $escaped = [];
    foreach ($params as $name => $value) {
        $escaped[$name] = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    return t($key, $escaped, $locale);
}

/** Replace {{name}} / {{ name }} placeholders. Unknown ones are left alone. */
function i18n_interpolate($string, array $params) {
    foreach ($params as $name => $value) {
        $string = preg_replace(
            '/\{\{\s*' . preg_quote((string)$name, '/') . '\s*\}\}/u',
            str_replace('$', '\\$', (string)$value),
            $string
        );
    }
    return $string;
}

/**
 * Human label for a DB status value ('pending', 'generated', 'sent',
 * 'failed', order financial statuses, …).
 *
 * Unknown values — Shopify can invent new financial statuses — degrade to the
 * capitalised raw value rather than rendering a bare key on the page.
 */
function t_status($status) {
    $status = strtolower(trim((string)$status));
    if ($status === '') {
        return '';
    }

    $key = 'status.' . $status;
    if (i18n_lookup($key, i18n_locale()) !== null) {
        return t($key);
    }

    return ucfirst(str_replace('_', ' ', $status));
}

// ---------------------------------------------------------------------------
// Locale-aware formatting (Intl, with graceful degradation)
// ---------------------------------------------------------------------------
//
// ext-intl is present on most PHP hosts but is not guaranteed. Every helper
// below checks for the class it needs and falls back to a sane, if less
// idiomatic, format rather than fataling.

/** True when ext-intl is available for date formatting. */
function i18n_has_intl_date() {
    return class_exists('IntlDateFormatter');
}

/** True when ext-intl is available for number/currency formatting. */
function i18n_has_intl_number() {
    return class_exists('NumberFormatter');
}

/**
 * Format a date in the active locale.
 *
 * $value accepts a timestamp, a DateTimeInterface, or anything strtotime()
 * understands (the DB stores 'Y-m-d H:i:s').
 * $style is 'short' | 'medium' | 'long' | 'full'.
 */
function fmt_date($value, $style = 'medium', $locale = null) {
    $ts = i18n_to_timestamp($value);
    if ($ts === null) {
        return '';
    }

    if (i18n_has_intl_date()) {
        $styles = [
            'short'  => IntlDateFormatter::SHORT,
            'medium' => IntlDateFormatter::MEDIUM,
            'long'   => IntlDateFormatter::LONG,
            'full'   => IntlDateFormatter::FULL,
        ];
        $dateStyle = $styles[$style] ?? IntlDateFormatter::MEDIUM;
        $formatter = new IntlDateFormatter(
            i18n_intl_locale($locale),
            $dateStyle,
            IntlDateFormatter::NONE
        );
        $out = $formatter->format($ts);
        if ($out !== false) {
            return $out;
        }
    }

    return date('M d, Y', $ts);
}

/** Format a date and time in the active locale. */
function fmt_datetime($value, $style = 'medium', $locale = null) {
    $ts = i18n_to_timestamp($value);
    if ($ts === null) {
        return '';
    }

    if (i18n_has_intl_date()) {
        $styles = [
            'short'  => IntlDateFormatter::SHORT,
            'medium' => IntlDateFormatter::MEDIUM,
            'long'   => IntlDateFormatter::LONG,
        ];
        $dateStyle = $styles[$style] ?? IntlDateFormatter::MEDIUM;
        $timeStyle = ($style === 'long') ? IntlDateFormatter::MEDIUM : IntlDateFormatter::SHORT;
        $formatter = new IntlDateFormatter(i18n_intl_locale($locale), $dateStyle, $timeStyle);
        $out = $formatter->format($ts);
        if ($out !== false) {
            return $out;
        }
    }

    return date('M d, Y H:i', $ts);
}

/** Format a plain number (thousands separators, decimal mark) for the locale. */
function fmt_number($value, $decimals = null, $locale = null) {
    $value = (float)$value;

    if (i18n_has_intl_number()) {
        $formatter = new NumberFormatter(i18n_intl_locale($locale), NumberFormatter::DECIMAL);
        if ($decimals !== null) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, (int)$decimals);
        }
        $out = $formatter->format($value);
        if ($out !== false) {
            return $out;
        }
    }

    return number_format($value, $decimals === null ? 0 : (int)$decimals);
}

/**
 * Format money in the active locale.
 *
 * $currency is an ISO code from Shopify ('USD', 'EUR', 'INR'). Intl places
 * the symbol where the locale expects it (1.234,56 € in German, $1,234.56 in
 * English), which is exactly what invoices need.
 */
function fmt_currency($value, $currency = 'USD', $locale = null) {
    $value    = (float)$value;
    $currency = strtoupper(trim((string)$currency)) ?: 'USD';

    if (i18n_has_intl_number()) {
        $formatter = new NumberFormatter(i18n_intl_locale($locale), NumberFormatter::CURRENCY);
        $out = $formatter->formatCurrency($value, $currency);
        if ($out !== false) {
            return $out;
        }
    }

    return $currency . ' ' . number_format($value, 2);
}

/**
 * Format a percentage. $value is the human-facing number (18 -> "18%"),
 * not a 0–1 ratio, because that is how the tax rates are already computed.
 */
function fmt_percent($value, $decimals = 0, $locale = null) {
    $value = (float)$value;

    if (i18n_has_intl_number()) {
        $formatter = new NumberFormatter(i18n_intl_locale($locale), NumberFormatter::PERCENT);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, (int)$decimals);
        $out = $formatter->format($value / 100);
        if ($out !== false) {
            return $out;
        }
    }

    return number_format($value, (int)$decimals) . '%';
}

/** Normalise the several date shapes used across the app to a timestamp. */
function i18n_to_timestamp($value) {
    if ($value instanceof DateTimeInterface) {
        return $value->getTimestamp();
    }
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts === false ? null : $ts;
    }
    return null;
}

// ---------------------------------------------------------------------------
// Persistence
// ---------------------------------------------------------------------------

/**
 * Whether the stores.app_locale column exists yet.
 *
 * The switcher has to survive a deploy that lands before the migration does,
 * so we probe once per request instead of assuming. Same defensive pattern
 * the packing-slip pages already use for their columns.
 */
function i18n_storage_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $cols = DBHelper::select("SHOW COLUMNS FROM `stores` LIKE 'app_locale'", "", []);
    $ready = !empty($cols);
    return $ready;
}

/**
 * Save the merchant's language choice against the store.
 *
 * Returns the normalised locale on success, or null when the requested
 * language isn't one we ship or the column isn't migrated yet — in both
 * cases nothing is written.
 */
function i18n_save_locale($shop_id, $locale) {
    $normalized = i18n_normalize_locale($locale);
    if ($normalized === null || !i18n_storage_ready()) {
        return null;
    }

    DBHelper::execute(
        "UPDATE stores SET app_locale = ? WHERE id = ?",
        "ss",
        [$normalized, $shop_id]
    );

    return $normalized;
}

// ---------------------------------------------------------------------------
// Email template defaults
// ---------------------------------------------------------------------------
//
// These deliberately use the app's existing SINGLE-brace variable syntax
// ({invoice_number}, {customer_name}, …) rather than i18next's {{...}}:
// merchants edit the subject and body by hand in Settings > Email, the help
// text documents single braces, and sendemail() substitutes them. t() leaves
// single braces untouched, so the two systems don't collide.

/** Default invoice email subject for the active locale. */
function i18n_default_email_subject() {
    $subject = t('invoice_email.subject');
    if ($subject === 'invoice_email.subject' && defined('DEFAULT_EMAIL_SUBJECT')) {
        return DEFAULT_EMAIL_SUBJECT;
    }
    return $subject;
}

/** Default invoice email body (HTML) for the active locale. */
function i18n_default_email_body() {
    $body = t('invoice_email.body');
    if ($body === 'invoice_email.body' && defined('DEFAULT_EMAIL_BODY')) {
        return DEFAULT_EMAIL_BODY;
    }
    return $body;
}

// ---------------------------------------------------------------------------
// Client-side bridge
// ---------------------------------------------------------------------------

/**
 * The subset of the catalog the browser needs (toasts, bulk-progress strings,
 * DataTables chrome), plus locale metadata.
 *
 * Only these keys cross into JS — the full catalog stays server-side so the
 * page weight doesn't grow with the number of translated screens.
 */
function i18n_js_keys() {
    return [
        'common.close',
        'common.date',
        'datatable.search',
        'datatable.length_menu',
        'datatable.info',
        'datatable.info_empty',
        'datatable.info_filtered',
        'datatable.first',
        'datatable.last',
        'datatable.next',
        'datatable.previous',
        'datatable.empty',
        'toast.invoice_processed',
        'toast.invoice_failed',
        'toast.email_failed',
        'toast.email_owner_failed',
        'toast.packing_slip_generated',
        'toast.packing_slip_failed',
        'toast.packing_slip_loading',
        'toast.packing_slip_load_failed',
        'toast.select_one_invoice',
        'toast.select_one_order',
        'toast.select_one_generated_slip',
        'toast.no_quota_remaining',
        'bulk.generating_title',
        'bulk.starting',
        'bulk.preparing',
        'bulk.keep_tab_open',
        'bulk.processing',
        'bulk.processed',
        'bulk.skipped',
        'bulk.stopped_title',
        'bulk.stopped_at',
        'bulk.stopping',
        'bulk.done_title',
        'bulk.all_generated',
        'bulk.finished_with_errors_title',
        'bulk.completed_failed',
        'bulk.will_refresh',
        'bulk.cap_reason',
        'bulk.quota_reason',
        'bulk.confirm_truncate',
        'bulk.confirm_cap',
        'bulk.noun_invoice',
        'bulk.noun_invoice_plural',
        'bulk.noun_packing_slip',
        'bulk.noun_packing_slip_plural',
        'bulk.generating_slips_title',
        'bulk.keep_tab_open_slips',
        'confirm.upgrade_now',
        'generic.generating',
    ];
}

/**
 * Emit the <script> block that hands translations to js/script.js.
 * Call from footer.php, before script.js loads.
 */
function i18n_js_payload() {
    $strings = [];
    foreach (i18n_js_keys() as $key) {
        $strings[$key] = t($key);
    }

    return [
        'locale' => i18n_locale(),
        'dir'    => i18n_dir(),
        'intl'   => str_replace('_', '-', i18n_intl_locale()),
        'rtl'    => i18n_is_rtl(),
        'strings' => $strings,
    ];
}
