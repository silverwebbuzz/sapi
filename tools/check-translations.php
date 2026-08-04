#!/usr/bin/env php
<?php
/**
 * Translation health check.
 *
 * Compares every shipped locale against the English catalog and reports:
 *   - keys missing from a locale        (they fall back to English at runtime)
 *   - keys a locale has but English doesn't (dead keys — usually a typo)
 *   - placeholder mismatches            ({{count}} dropped or renamed)
 *   - values left identical to English  (probably untranslated)
 *   - malformed JSON
 *
 * Run before every release:
 *     php tools/check-translations.php
 *     php tools/check-translations.php --strict     # untranslated values fail too
 *
 * Exit code 0 = healthy, 1 = problems found. Safe to wire into CI.
 */

require_once __DIR__ . '/../invoice/i18n.php';

$strict = in_array('--strict', $argv, true);

$localesPath = i18n_locales_path();
$locales     = i18n_locales();

// ---------------------------------------------------------------------------
// Load English, the reference catalog
// ---------------------------------------------------------------------------

$enPath = $localesPath . '/en/common.json';
if (!is_readable($enPath)) {
    fwrite(STDERR, "FATAL: no English catalog at $enPath\n");
    exit(1);
}
$en = json_decode(file_get_contents($enPath), true);
if (!is_array($en)) {
    fwrite(STDERR, "FATAL: en/common.json is not valid JSON: " . json_last_error_msg() . "\n");
    exit(1);
}

/** Extract the {{placeholder}} names used in a string. */
function placeholders($string) {
    preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', (string)$string, $m);
    $names = array_unique($m[1]);
    sort($names);
    return $names;
}

$enKeys = array_keys($en);
$problems = 0;
$rows = [];

echo "Reference: en (" . count($enKeys) . " keys)\n\n";

foreach ($locales as $code => $meta) {
    if ($code === 'en') {
        continue;
    }

    $path = $localesPath . '/' . $code . '/common.json';
    $label = str_pad($code, 6);

    if (!is_readable($path)) {
        echo "$label  MISSING FILE  ($path)\n";
        $problems++;
        continue;
    }

    $catalog = json_decode(file_get_contents($path), true);
    if (!is_array($catalog)) {
        echo "$label  INVALID JSON  (" . json_last_error_msg() . ")\n";
        $problems++;
        continue;
    }

    $missing   = array_values(array_diff($enKeys, array_keys($catalog)));
    $extra     = array_values(array_diff(array_keys($catalog), $enKeys));
    $badPlace  = [];
    $identical = [];

    foreach ($en as $key => $value) {
        if (!isset($catalog[$key])) {
            continue;
        }
        if (placeholders($value) !== placeholders($catalog[$key])) {
            $badPlace[] = $key;
        }
        // Short shared tokens (SKU, Barcode, brand names) legitimately match.
        if ($catalog[$key] === $value && mb_strlen($value) > 12) {
            $identical[] = $key;
        }
    }

    $isProblem = $missing || $extra || $badPlace || ($strict && $identical);
    if ($isProblem) {
        $problems++;
    }

    $status = $isProblem ? 'FAIL' : ' ok ';
    printf(
        "%s  %s  %d keys | missing %d | extra %d | placeholder %d | same-as-en %d\n",
        $label, $status, count($catalog),
        count($missing), count($extra), count($badPlace), count($identical)
    );

    foreach ($missing as $k)  { echo "          missing:     $k\n"; }
    foreach ($extra as $k)    { echo "          not in en:   $k\n"; }
    foreach ($badPlace as $k) {
        echo "          placeholder: $k  (en: " . implode(',', placeholders($en[$k]))
           . " | $code: " . implode(',', placeholders($catalog[$k])) . ")\n";
    }
    if ($strict) {
        foreach ($identical as $k) { echo "          same as en:  $k\n"; }
    }
}

echo "\n";
if ($problems === 0) {
    echo "All locales healthy.\n";
    exit(0);
}

echo "$problems locale(s) need attention.\n";
exit(1);
