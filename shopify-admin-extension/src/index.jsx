import React from 'react';
import {extend, render, Button, BlockStack, InlineStack, Text, Spinner, useSessionToken} from '@shopify/admin-ui-extensions-react';
import {useEffect, useState} from 'react';

const BACKEND_BASE_URL = 'https://sapi.silverwebbuzz.com'; // Replace with your actual app backend domain if needed.

function getQueryParam(name) {
  const params = new URLSearchParams(window.location.search);
  return params.get(name) || '';
}

function useOrderContext() {
  const [orderId, setOrderId] = useState('');
  const [shopDomain, setShopDomain] = useState('');

  useEffect(() => {
    const orderFromQuery = getQueryParam('order_id') || getQueryParam('resource_id') || getQueryParam('id');
    const shopFromQuery = getQueryParam('shop') || getQueryParam('shop_origin') || getQueryParam('domain');
    setOrderId(orderFromQuery);
    setShopDomain(shopFromQuery);
  }, []);

  return {orderId, shopDomain};
}

function PrintInvoiceAction() {
  const sessionToken = useSessionToken();
  const {orderId, shopDomain} = useOrderContext();
  const [loading, setLoading] = useState(false);
  const [feedback, setFeedback] = useState('');

  async function handlePrint() {
    setFeedback('');

    if (!orderId || !shopDomain) {
      setFeedback('Unable to detect the order ID or shop domain. Open this action from a Shopify order page.');
      return;
    }

    setLoading(true);
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      setFeedback('Popup blocked. Please allow popups for this app.');
      setLoading(false);
      return;
    }

    try {
      const headers = {
        Accept: 'application/pdf'
      };

      try {
        const token = await sessionToken.getSessionToken();
        if (token) {
          headers.Authorization = `Bearer ${token}`;
        }
      } catch (tokenError) {
        console.warn('Unable to get session token from extension API:', tokenError);
      }

      const url = `${BACKEND_BASE_URL}/invoice/admin-print-invoice.php?order_id=${encodeURIComponent(orderId)}&shop=${encodeURIComponent(shopDomain)}`;
      const response = await fetch(url, {
        method: 'GET',
        headers
      });

      if (!response.ok) {
        const text = await response.text();
        throw new Error(text || `Server returned ${response.status}`);
      }

      const blob = await response.blob();
      const pdfUrl = URL.createObjectURL(blob);
      printWindow.location.href = pdfUrl;
      printWindow.focus();
    } catch (error) {
      setFeedback(`Unable to generate invoice: ${error.message}`);
      printWindow.close();
    } finally {
      setLoading(false);
    }
  }

  return (
    <BlockStack spacing="loose">
      <InlineStack alignment="space-between">
        <Button onPress={handlePrint} loading={loading} primary>
          Print Invoice - SWB Auto PDF Invoices
        </Button>
      </InlineStack>
      {loading && (
        <InlineStack alignment="center" spacing="tight">
          <Spinner size="small" accessibilityLabel="Generating invoice" />
          <Text>Generating your invoice...</Text>
        </InlineStack>
      )}
      {feedback && <Text tone="critical">{feedback}</Text>}
      <Text tone="secondary">
        This button sends the Shopify order ID and shop domain to your existing PHP backend, which generates or retrieves the PDF invoice and opens it for view/print.
      </Text>
    </BlockStack>
  );
}

extend('Admin::Action::Order', render(() => <PrintInvoiceAction />));
extend('Admin::Action::Orders::Index', render(() => <PrintInvoiceAction />));
