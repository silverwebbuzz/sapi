/**
 * Minimal i18n for the admin-action extension.
 *
 * The extension is a separate bundle from the PHP app, so it can't read
 * /locales off disk at runtime. Instead it imports the same catalogs at build
 * time and picks the locale from Shopify's `i18n.locale` API.
 *
 * Only the `extension.*` keys are pulled from each catalog — the extension is
 * one small panel, and shipping 19 full app catalogs into an admin action
 * would be wasteful. Keeping the keys in the shared /locales files means the
 * extension and the app never drift apart or get translated twice.
 */

import en from './locales/en.json';
import de from './locales/de.json';
import fr from './locales/fr.json';
import es from './locales/es.json';
import it from './locales/it.json';
import nl from './locales/nl.json';
import pt from './locales/pt.json';
import pl from './locales/pl.json';
import cs from './locales/cs.json';
import da from './locales/da.json';
import sv from './locales/sv.json';
import nb from './locales/nb.json';
import fi from './locales/fi.json';
import ja from './locales/ja.json';
import ko from './locales/ko.json';
import zhCN from './locales/zh-CN.json';
import zhTW from './locales/zh-TW.json';
import ar from './locales/ar.json';
import he from './locales/he.json';

const CATALOGS = {
  en, de, fr, es, it, nl, pt, pl, cs, da, sv, nb, fi, ja, ko,
  'zh-CN': zhCN,
  'zh-TW': zhTW,
  ar, he,
};

const FALLBACK = 'en';

/** Locales that lay out right to left. */
const RTL = new Set(['ar', 'he']);

/**
 * Map a Shopify locale tag ("de-DE", "zh-Hans", "pt-BR") onto a catalog we
 * ship. Mirrors i18n_normalize_locale() in invoice/i18n.php — keep the two
 * in step when adding a language.
 */
export function normalizeLocale(tag) {
  if (!tag) return FALLBACK;

  const normalized = String(tag).replace(/_/g, '-');

  // Exact match first ("zh-CN").
  const exact = Object.keys(CATALOGS).find(
    (code) => code.toLowerCase() === normalized.toLowerCase(),
  );
  if (exact) return exact;

  const [rawLang, rawRegion = ''] = normalized.split('-');
  const lang = rawLang.toLowerCase();
  const region = rawRegion.toLowerCase();

  if (lang === 'zh') {
    return ['tw', 'hk', 'mo', 'hant'].includes(region) ? 'zh-TW' : 'zh-CN';
  }
  if (['no', 'nb', 'nn'].includes(lang)) return 'nb';
  if (lang === 'iw') return 'he';

  return CATALOGS[lang] ? lang : FALLBACK;
}

export function isRtl(locale) {
  return RTL.has(locale);
}

/**
 * Build a translator bound to one locale.
 *
 * Missing keys fall back to English, then to the key itself — the same
 * contract as the PHP t(), so a gap shows up as a visible key rather than
 * an empty panel.
 */
export function createTranslator(rawLocale) {
  const locale = normalizeLocale(rawLocale);
  const catalog = CATALOGS[locale] || CATALOGS[FALLBACK];
  const fallback = CATALOGS[FALLBACK];

  return function t(key, params) {
    let value = catalog[key] ?? fallback[key];

    if (value === undefined) {
      // eslint-disable-next-line no-console
      console.warn('[i18n] missing extension key:', key, '(locale:', locale, ')');
      return key;
    }

    if (params) {
      Object.keys(params).forEach((name) => {
        value = value.replace(
          new RegExp(`\\{\\{\\s*${name}\\s*\\}\\}`, 'g'),
          params[name],
        );
      });
    }

    return value;
  };
}
