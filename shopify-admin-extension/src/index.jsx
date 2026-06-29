import React, {useEffect, useState} from 'react';
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
  const {close, data, query} = useApi(TARGET);

  const [shopDomain, setShopDomain] = useState('');
  const [error, setError] = useState('');
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
        if (active) setError('Unable to detect the shop domain.');
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
            Open / Print Invoice
          </Button>
        ) : undefined
      }
      secondaryAction={<Button onPress={close}>Close</Button>}
    >
      <BlockStack gap="base">
        {loading && (
          <BlockStack inlineAlignment="center">
            <ProgressIndicator size="small-200" variant="spinner" />
            <Text>Preparing your invoice…</Text>
          </BlockStack>
        )}

        {error && <Banner tone="critical">{error}</Banner>}

        {!loading && !error && !orderId && (
          <Banner tone="critical">
            Could not detect the order. Open this action from an order details page.
          </Banner>
        )}

        {ready && (
          <BlockStack gap="base">
            <Text>
              Click the button below to open the PDF invoice for this order in a
              new tab, ready to view or print.
            </Text>
            <Link href={invoiceUrl} target="_blank">
              Open invoice PDF
            </Link>
          </BlockStack>
        )}
      </BlockStack>
    </AdminAction>
  );
}
