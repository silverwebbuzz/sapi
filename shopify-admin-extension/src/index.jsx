import React, {useEffect, useMemo, useState} from 'react';
import {createTranslator} from './i18n';
import {
  reactExtension,
  useApi,
  AdminAction,
  Button,
  Link,
  BlockStack,
  Text,
  Banner,
  ProgressIndicator,
} from '@shopify/ui-extensions-react/admin';

const TARGET = 'admin.order-details.action.render';

// Your app backend that generates / serves the PDF invoice.
const BACKEND_BASE_URL = 'https://sapi.silverwebbuzz.com';

export default reactExtension(TARGET, () => <PrintInvoiceAction />);

// gid://shopify/Order/123456789 -> "123456789"
function numericId(gid) {
  if (!gid) return '';
  const parts = String(gid).split('/');
  return parts[parts.length - 1] || '';
}

function PrintInvoiceAction() {
  const {close, data, query, i18n} = useApi(TARGET);

  // Shopify hands us the admin user's locale; the translator falls back to
  // English for anything we don't ship.
  const t = useMemo(() => createTranslator(i18n?.locale), [i18n?.locale]);

  const [shopDomain, setShopDomain] = useState('');
  const [errorKey, setErrorKey] = useState('');
  const [loading, setLoading] = useState(true);

  const orderId = numericId(data?.selected?.[0]?.id);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const res = await query(`{ shop { myshopifyDomain } }`);
        const domain = res?.data?.shop?.myshopifyDomain || '';
        if (active) setShopDomain(domain);
      } catch (e) {
        // Store the KEY, not the translated text, so the message re-renders
        // in the right language if the locale changes.
        if (active) setErrorKey('extension.error_shop_domain');
      } finally {
        if (active) setLoading(false);
      }
    })();
    return () => {
      active = false;
    };
  }, [query]);

  const ready = Boolean(orderId && shopDomain);
  const invoiceUrl = ready
    ? `${BACKEND_BASE_URL}/invoice/admin-print-invoice.php?order_id=${encodeURIComponent(
        orderId,
      )}&shop=${encodeURIComponent(shopDomain)}`
    : '';

  return (
    <AdminAction
      primaryAction={
        ready ? (
          <Button href={invoiceUrl} target="_blank" variant="primary">
            {t('extension.open_print_invoice')}
          </Button>
        ) : undefined
      }
      secondaryAction={<Button onPress={close}>{t('extension.close')}</Button>}
    >
      <BlockStack gap="base">
        {loading && (
          <BlockStack inlineAlignment="center">
            <ProgressIndicator size="small-200" variant="spinner" />
            <Text>{t('extension.preparing')}</Text>
          </BlockStack>
        )}

        {errorKey && <Banner tone="critical">{t(errorKey)}</Banner>}

        {!loading && !errorKey && !orderId && (
          <Banner tone="critical">{t('extension.error_no_order')}</Banner>
        )}

        {ready && (
          <BlockStack gap="base">
            <Text>{t('extension.instructions')}</Text>
            <Link href={invoiceUrl} target="_blank">
              {t('extension.open_invoice_pdf')}
            </Link>
          </BlockStack>
        )}
      </BlockStack>
    </AdminAction>
  );
}
