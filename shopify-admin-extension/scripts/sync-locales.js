#!/usr/bin/env node
/**
 * Copy the `extension.*` keys out of the shared /locales catalogs into
 * src/locales/<code>.json, which the extension bundle imports.
 *
 * /locales is the single source of truth for every string in the product.
 * The extension can't read it at runtime (it ships as a bundle, not as files
 * on our server), so this runs at build time instead of duplicating strings
 * by hand.
 *
 *   npm run sync-locales      # also runs automatically before build/dev
 *
 * Locales listed in LOCALES but missing a catalog are reported and skipped
 * rather than failing the build — the extension falls back to English for
 * them at runtime.
 */

const fs = require('fs');
const path = require('path');

// Keep in step with i18n_locales() in invoice/i18n.php.
const LOCALES = [
  'en', 'de', 'fr', 'es', 'it', 'nl', 'pt', 'pl', 'cs', 'da',
  'sv', 'nb', 'fi', 'ja', 'ko', 'zh-CN', 'zh-TW', 'ar', 'he',
];

const PREFIX = 'extension.';

const repoRoot = path.resolve(__dirname, '..', '..');
const sourceDir = path.join(repoRoot, 'locales');
const targetDir = path.join(__dirname, '..', 'src', 'locales');

fs.mkdirSync(targetDir, { recursive: true });

let written = 0;
const missing = [];

for (const locale of LOCALES) {
  const sourcePath = path.join(sourceDir, locale, 'common.json');

  if (!fs.existsSync(sourcePath)) {
    missing.push(locale);
    // Still emit an empty catalog so the bundle's static imports resolve.
    fs.writeFileSync(path.join(targetDir, `${locale}.json`), '{}\n');
    continue;
  }

  const catalog = JSON.parse(fs.readFileSync(sourcePath, 'utf8'));
  const subset = {};

  for (const [key, value] of Object.entries(catalog)) {
    if (key.startsWith(PREFIX)) {
      subset[key] = value;
    }
  }

  fs.writeFileSync(
    path.join(targetDir, `${locale}.json`),
    JSON.stringify(subset, null, 2) + '\n',
  );
  written++;
}

console.log(`[sync-locales] wrote ${written} catalog(s) to src/locales/`);
if (missing.length) {
  console.warn(
    `[sync-locales] no /locales entry for: ${missing.join(', ')} ` +
      '(these fall back to English at runtime)',
  );
}
