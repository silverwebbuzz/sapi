<?php
// Every helper below (PDF, packing slip, email, limit notices) renders
// user-facing strings through i18n, so the catalog must be loaded here rather
// than left to the caller — non-page entry points like webhook.php include
// this file without ever touching header.php.
require_once __DIR__ . '/i18n.php';

// Import Dom PDF classes
use Dompdf\Dompdf;
use Dompdf\Options;

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Pull the recipient's email + name out of a Shopify order payload.
 *
 * `customer` is absent on guest checkouts and on payloads where Shopify
 * redacts customer data, so relying on `customer.email` alone leaves the
 * invoice row with an empty address and every automatic email fails inside
 * PHPMailer. Shopify still exposes the buyer's address as `email` /
 * `contact_email` in those cases; the name falls back to the billing and then
 * shipping address.
 */
function extractOrderContact(array $order) {
    $email = '';
    foreach ([$order['customer']['email'] ?? '', $order['email'] ?? '', $order['contact_email'] ?? ''] as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '') {
            $email = $candidate;
            break;
        }
    }

    $name = trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''));
    if ($name === '') {
        foreach (['billing_address', 'shipping_address'] as $addr) {
            $candidate = trim((string)($order[$addr]['name'] ?? ''));
            if ($candidate === '') {
                $candidate = trim(($order[$addr]['first_name'] ?? '') . ' ' . ($order[$addr]['last_name'] ?? ''));
            }
            if ($candidate !== '') {
                $name = $candidate;
                break;
            }
        }
    }

    return ['email' => $email, 'name' => $name];
}

function generatepdf($shop_id,$order_id){
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ? ",
        "s",
        [$shop_id]
    );

    if ($shop_data) {
        // The PDF is customer-facing, so it renders in the store's language
        // regardless of who triggered generation (merchant click, webhook,
        // or bulk batch).
        i18n_boot($shop_data);
        $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
        $invoice_table = "invoices_" . $shop_name;
    
        
        // Fetch invoice details
        $invoice = DBHelper::selectOne("SELECT * FROM `$invoice_table` WHERE order_id = ?","s", [$order_id]);
    
        if ($invoice) {
            
            if(isset($_GET['invoicestatus']) && $_GET['invoicestatus']=='generated')
            {
                ?>
                <embed src="data:application/pdf;base64,<?= $invoice['pdf_invoice']; ?>" type="application/pdf" width="100%" height="100%" />
                <?php
                exit;
            }
            // Decode JSON data
            $billing_address = json_decode($invoice['billing_address'], true);
            $shipping_address = json_decode($invoice['shipping_address'], true);
            $products = json_decode($invoice['products'], true);
    
            // Prepare order items HTML
            $items_html = '';
            $counter = 1;
            $subtotal_ex_tax = 0.0;
            $item_tax_total = 0.0;

            // Aggregate tax by title across all line items. Indian GST split
            // (CGST + SGST) lands as two separate entries; UK VAT lands as one.
            // Each entry tracks the rate and the running total of tax_amount.
            $tax_aggregates = [];   // key = "TITLE|RATE" => ['title','rate','amount']

            foreach ($products as $item) {
                $line_tax_rate_combined = 0.0;   // sum of all tax_lines.rate on this item
                $line_tax_amount_total  = 0.0;   // sum of all tax_lines.price on this item

                if (isset($item['tax_lines']) && is_array($item['tax_lines'])) {
                    foreach ($item['tax_lines'] as $tl) {
                        $r = isset($tl['rate'])  ? floatval($tl['rate'])  : 0.0;
                        $p = isset($tl['price']) ? floatval($tl['price']) : 0.0;
                        $t = isset($tl['title']) ? (string)$tl['title']   : 'Tax';

                        $line_tax_rate_combined += $r;
                        $line_tax_amount_total  += $p;

                        // Round rate to 4dp for the aggregate key so 0.09000000001 and 0.09
                        // bucket together.
                        $rateKey = number_format($r, 4, '.', '');
                        $key = $t . '|' . $rateKey;
                        if (!isset($tax_aggregates[$key])) {
                            $tax_aggregates[$key] = ['title' => $t, 'rate' => $r, 'amount' => 0.0];
                        }
                        $tax_aggregates[$key]['amount'] += $p;
                    }
                }

                $unit_price = isset($item['price_set']['shop_money']['amount']) ? floatval($item['price_set']['shop_money']['amount']) : floatval($item['price']);
                $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 1.0;
                $line_total_gross = $unit_price * $quantity;

                // Shopify's `price` is tax-inclusive. Use the COMBINED tax rate
                // across all tax_lines so Indian GST (9% + 9% = 18%) is handled
                // correctly. For UK VAT this is just the single 20%.
                if ($line_tax_rate_combined > 0) {
                    $unit_price_ex_tax = $unit_price / (1 + $line_tax_rate_combined);
                } elseif ($line_tax_amount_total > 0) {
                    $unit_price_ex_tax = max(0, ($line_total_gross - $line_tax_amount_total) / max(1.0, $quantity));
                } else {
                    $unit_price_ex_tax = $unit_price;
                }
                $line_total_ex_tax = $unit_price_ex_tax * $quantity;
                $subtotal_ex_tax += $line_total_ex_tax;
                $item_tax_total  += $line_tax_amount_total;

                // Build description from variant title and properties
                $description_parts = [];
                if (!empty($item['variant_title'])) {
                    $description_parts[] = $item['variant_title'];
                }
                if (!empty($item['properties']) && is_array($item['properties'])) {
                    foreach ($item['properties'] as $property) {
                        $prop_name = $property['name'] ?? '';
                        if ($prop_name === '' || $prop_name[0] === '_') {
                            continue;
                        }
                        $description_parts[] = $prop_name . ': ' . ($property['value'] ?? '');
                    }
                }
                $description = !empty($description_parts) ? implode(' | ', $description_parts) : '';

                // Per-row tax column shows the COMBINED effective rate.
                // (e.g. India intra-state: 18% rather than just the 9% CGST.)
                $row_tax_text = $line_tax_rate_combined > 0
                    ? rtrim(rtrim(number_format($line_tax_rate_combined * 100, 2, '.', ''), '0'), '.') . '%'
                    : '';

                $items_html .= '<tr>';
                $items_html .= '<td>'.htmlspecialchars($item['name']).'</td>';
                $items_html .= '<td>'.htmlspecialchars($description).'</td>';
                $items_html .= '<td class="num">'.htmlspecialchars(fmt_currency($unit_price_ex_tax, $invoice['currency'])).'</td>';
                $items_html .= '<td class="qty">'.htmlspecialchars(fmt_number($quantity, 0)).'</td>';
                $items_html .= '<td>'.htmlspecialchars($row_tax_text).'</td>';
                $items_html .= '<td class="num">'.htmlspecialchars(fmt_currency($line_total_ex_tax, $invoice['currency'])).'</td>';
                $items_html .= '</tr>';
                $counter++;
            }

            $shipping_tax_amount = floatval($invoice['tax_amount']) - $item_tax_total;
            if ($shipping_tax_amount < 0) {
                $shipping_tax_amount = 0;
            }

            $has_tax = floatval($invoice['tax_amount']) > 0;

            // Build the tax block: one <tr> per tax title (CGST, SGST, VAT, etc.).
            // Empty for tax-free orders.
            $tax_block = '';
            foreach ($tax_aggregates as $agg) {
                $ratePct = rtrim(rtrim(number_format($agg['rate'] * 100, 2, '.', ''), '0'), '.');
                $label = $agg['title'] . ' (' . $ratePct . '%)';
                $tax_block .= '<tr><td class="label">' . htmlspecialchars($label) . '</td>'
                            . '<td class="value">' . htmlspecialchars(fmt_currency($agg['amount'], $invoice['currency'])) . '</td></tr>';
            }

            // Column-header label & shipping-tax label use the FIRST tax title
            // we saw (UK: "VAT"; India: "CGST" — close enough for the column
            // header; the per-row column shows the combined % anyway).
            $first_agg = !empty($tax_aggregates) ? reset($tax_aggregates) : null;
            $primary_tax_title = $first_agg ? $first_agg['title'] : 'Tax';
            $primary_tax_rate  = $first_agg ? $first_agg['rate']  : null;
            $primary_rate_text = $primary_tax_rate !== null
                ? ' (' . rtrim(rtrim(number_format($primary_tax_rate * 100, 2, '.', ''), '0'), '.') . '%)'
                : '';
            $tax_column_label = $has_tax ? $primary_tax_title . $primary_rate_text : '';

            // Backward-compat single-line placeholders (older custom templates
            // may still reference these). Empty when we have a multi-tax block.
            $tax_label_full     = $has_tax ? $primary_tax_title . $primary_rate_text : '';
            $tax_amount_display = $has_tax ? fmt_currency($invoice['tax_amount'], $invoice['currency']) : '';

            // Shipping-tax label uses the combined GST rate when the country
            // splits the tax, otherwise the primary single rate.
            $combined_shipping_rate = 0.0;
            foreach ($tax_aggregates as $agg) { $combined_shipping_rate += $agg['rate']; }
            if ($has_tax) {
                $lc_tax_label = strtolower($primary_tax_title);
                // The tax NAME (GST, VAT, …) comes from Shopify and stays as-is;
                // only the surrounding "Shipping …" wording is translated.
                if (strpos($lc_tax_label, 'gst') !== false) {
                    // For Indian GST, label the shipping tax with the combined rate (18%).
                    $shipRatePct = rtrim(rtrim(number_format($combined_shipping_rate * 100, 2, '.', ''), '0'), '.');
                    $shipping_tax_label = t('invoice.shipping_tax', ['tax' => 'GST (' . $shipRatePct . '%)']);
                } elseif (strpos($lc_tax_label, 'vat') !== false) {
                    $shipping_tax_label = t('invoice.shipping_tax', ['tax' => 'VAT' . $primary_rate_text]);
                } else {
                    $shipping_tax_label = t('invoice.shipping_tax', ['tax' => $primary_tax_title . $primary_rate_text]);
                }
            } else {
                $shipping_tax_label = '';
            }

            $discount_row = '';
            if (floatval($invoice['discount_amount']) != 0) {
                $discount_amount = abs(floatval($invoice['discount_amount']));
                $discount_row = '<tr><td class="label">' . e('invoice.discount') . '</td><td class="value">-'
                    . htmlspecialchars(fmt_currency($discount_amount, $invoice['currency'])) . '</td></tr>';
            }

            $shipping_tax_block = '';
            if ($shipping_tax_amount > 0) {
                $shipping_tax_block = '<tr><td class="label">' . htmlspecialchars($shipping_tax_label) . '</td><td class="value">'
                    . htmlspecialchars(fmt_currency($shipping_tax_amount, $invoice['currency'])) . '</td></tr>';
            }
    
            // Prepare replacements array
            $replacements = [
                '{{ Company_Logo }}' => !empty($shop_data['logo_url']) ? 
                    $shop_data['logo_url'] : 
                    '',  // Empty string will make the image not show, and alt text will be used
                '{{ Company_Name }}' => $shop_data['store_name'],
                '{{ Company_Address }}' => $shop_data['address1']."<br/>".$shop_data['address2']."<br/>".$shop_data['city']." ".$shop_data['province']." ".$shop_data['province_code']." ".$shop_data['zip']."<br/>".$shop_data['country_name'] ,
                '{{ Company_Phone }}' => $shop_data['phone'],
                '{{ Company_Email }}' => $shop_data['email'],
                '{{ Company_GSTIN }}' => $shop_data['gstin'] ?? '',
                '{{ Order_Number }}' => $invoice['order_name'],
                '{{ Invoice_Date }}' => fmt_date($invoice['created_at'], 'short'),
                '{{ Due_Date }}' => fmt_date($invoice['created_at'].' +15 days', 'short'),
                '{{ Billing_Name }}' => $billing_address['name'] ?? '',
                '{{ Billing_Address1 }}' => $billing_address['address1'] ?? '',
                '{{ Billing_Address2 }}' => $billing_address['address2'] ?? '',
                '{{ Billing_City }}' => $billing_address['city'] ?? '',
                '{{ Billing_State }}' => $billing_address['province'] ?? '',
                '{{ Billing_Zip }}' => $billing_address['zip'] ?? '',
                '{{ Billing_Country }}' => $billing_address['country'] ?? '',
                '{{ Billing_GSTIN }}' => '', // Add GSTIN if available
                '{{ Billing_Email }}' => $invoice['customer_email'] ?? '',
                '{{ Billing_Phone }}' => $billing_address['phone'] ?? t('invoice.no_phone'),
                '{{ Shipping_Name }}' => isset($shipping_address['name']) ? $shipping_address['name'] : ($billing_address['name'] ?? ''),
                '{{ Shipping_Address1 }}' => isset($shipping_address['address1']) ? $shipping_address['address1'] : ($billing_address['address1'] ?? ''),
                '{{ Shipping_Address2 }}' => isset($shipping_address['address2']) ? $shipping_address['address2'] : ($billing_address['address2'] ?? ''),
                '{{ Shipping_City }}' => isset($shipping_address['city']) ? $shipping_address['city'] : ($billing_address['city'] ?? ''),
                '{{ Shipping_State }}' => isset($shipping_address['province']) ? $shipping_address['province'] : ($billing_address['province'] ?? ''),
                '{{ Shipping_Zip }}' => isset($shipping_address['zip']) ? $shipping_address['zip'] : ($billing_address['zip'] ?? ''),
                '{{ Shipping_Country }}' => isset($shipping_address['country']) ? $shipping_address['country'] : ($billing_address['country'] ?? ''),
                '{{ Order_Items }}' => $items_html,
                '{{ Subtotal }}' => fmt_currency($subtotal_ex_tax, $invoice['currency']),
                '{{ Tax_Column_Label }}' => htmlspecialchars($tax_column_label),
                '{{ Tax_Block }}' => $tax_block,
                // Back-compat placeholders for older templates that still
                // reference a single Tax_Label / Tax_Amount pair.
                '{{ Tax_Label }}' => htmlspecialchars($tax_label_full),
                '{{ Tax_Amount }}' => $tax_amount_display,
                '{{ Shipping_Cost }}' => fmt_currency($invoice['shipping_cost'], $invoice['currency']),
                '{{ Shipping_Tax_Block }}' => $shipping_tax_block,
                '{{ Discount_Block }}' => $discount_row,
                '{{ Total_Amount }}' => fmt_currency($invoice['total_price'], $invoice['currency']),
                '{{ Payment_Method }}' => $invoice['payment_method'] ?? t('invoice.unknown_payment'),
                '{{ Payment_Status }}' => t_status($invoice['order_status']),

                // Drives <html lang dir> in the template, so dompdf lays the
                // PDF out right-to-left for Arabic and Hebrew.
                '{{ Html_Lang }}' => i18n_locale(),
                '{{ Html_Dir }}'  => i18n_dir(),

                // Translated chrome. Templates carry {{ L_* }} placeholders
                // instead of English literals, so one set of HTML files serves
                // every language and adding a language needs no template edit.
                '{{ L_Invoice_Title }}' => e('invoice.title_display'),
                '{{ L_Invoice_No }}'    => e('invoice.invoice_no'),
                '{{ L_Date }}'          => e('invoice.date'),
                '{{ L_Due_Date }}'      => e('invoice.due_date'),
                '{{ L_Merchant }}'      => e('invoice.merchant'),
                '{{ L_Bill_To }}'       => e('invoice.bill_to'),
                '{{ L_Ship_To }}'       => e('invoice.ship_to'),
                '{{ L_Item }}'          => e('invoice.item'),
                '{{ L_Description }}'   => e('invoice.description'),
                '{{ L_Price }}'         => e('invoice.price'),
                '{{ L_Qty }}'           => e('invoice.qty'),
                '{{ L_Total }}'         => e('invoice.total'),
                '{{ L_Payment_Info }}'  => e('invoice.payment_info'),
                '{{ L_Subtotal }}'      => e('invoice.subtotal'),
                '{{ L_Shipping }}'      => e('invoice.shipping'),
                '{{ L_Grand_Total }}'   => e('invoice.grand_total'),
                '{{ L_Notes }}'         => e('invoice.notes'),
                '{{ L_Terms }}'         => e('invoice.terms'),
                '{{ L_Thank_You }}'     => e('invoice.thank_you'),
                '{{ L_Note }}'          => e('invoice.computer_generated'),
            ];
    
            // Load HTML template
            $template_id = $shop_data['invoice_templates_id'];
            // Fetch template details
            $template_html = DBHelper::selectOne(
                "SELECT * FROM `invoice_templates` WHERE id = ?",
                "s", 
                [$template_id]
            );
            
            $template = file_get_contents('invoice_templates/html/'.$template_html['template_file']);
            $html = str_replace(array_keys($replacements), array_values($replacements), $template);
    
            // Create new PDF document
            // Set options (optional but useful)
            $options = new Options();
            $options->set('isRemoteEnabled', true); // Enable external images (if needed)
            $options->set('defaultFont', 'DejaVu Sans'); // Set default font
            $options->set('dpi', 96); // DomPDF's default — keeps CSS px sizes visually correct on A4

            // Initialize DomPDF with options
            $dompdf = new Dompdf($options);

            // Load HTML content
            $dompdf->loadHtml($html);

            // (Optional) Set paper size and orientation
            $dompdf->setPaper('A4', 'portrait'); // or 'landscape'

            // Render the HTML as PDF
            $dompdf->render();

            // Output PDF to browser
            //$dompdf->stream('invoice_'.$invoice['order_name'].'.pdf', array("Attachment" => false));

            // Or to string
            $pdf_output = $dompdf->output();
            $encoded_pdf = base64_encode($pdf_output); // Encode for storage
     
            // Single database update for both PDF and email status
            $affectedRows = DBHelper::execute(
                "UPDATE `$invoice_table` SET  invoice_status = 'generated', pdf_invoice = ? WHERE order_id = ? ",
                "ss",
                [$encoded_pdf, $order_id]
            );
            $affectedRows = DBHelper::execute(
                "UPDATE `store_subscriptions` SET  order_used = order_used+1  WHERE store_id = ? ",
                "s",
                [$shop_id]
            );
            return t('errors.pdf_generated');

        } else {
            return t('errors.invoice_not_found');
        }
    } else {
        return t('errors.shop_not_found');
    }
}

function getInvoicePdfContent($shop_id, $order_id) {
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ?",
        "s",
        [$shop_id]
    );

    if (!$shop_data) {
        return null;
    }

    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
    $invoice_table = "invoices_" . $shop_name;

    $invoice = DBHelper::selectOne(
        "SELECT pdf_invoice FROM `$invoice_table` WHERE order_id = ?",
        "s",
        [$order_id]
    );

    if ($invoice && !empty($invoice['pdf_invoice'])) {
        return base64_decode($invoice['pdf_invoice']);
    }

    if ($invoice) {
        generatepdf($shop_id, $order_id);
        $invoice = DBHelper::selectOne(
            "SELECT pdf_invoice FROM `$invoice_table` WHERE order_id = ?",
            "s",
            [$order_id]
        );

        if ($invoice && !empty($invoice['pdf_invoice'])) {
            return base64_decode($invoice['pdf_invoice']);
        }
    }

    return null;
}

/**
 * Generate a packing-slip PDF for one order and store it in the
 * packing_slip_pdf column of invoices_<shop>.
 *
 * Notes:
 *  - Does NOT count against the plan's order_limit. Packing slips are
 *    operational documents, not billable invoices.
 *  - Same row, separate column from the invoice PDF.
 *  - Reuses the same dompdf options as generatepdf().
 */
function generatePackingSlip($shop_id, $order_id) {
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ?",
        "s",
        [$shop_id]
    );
    if (!$shop_data) {
        return ['status' => 'error', 'message' => t('errors.shop_not_found')];
    }

    // Packing slips are customer/warehouse facing — render in the store's language.
    i18n_boot($shop_data);

    $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
    $invoice_table = "invoices_" . $shop_name;

    $invoice = DBHelper::selectOne(
        "SELECT * FROM `$invoice_table` WHERE order_id = ?",
        "s",
        [$order_id]
    );
    if (!$invoice) {
        return ['status' => 'error', 'message' => t('errors.order_not_found')];
    }

    // Build the items rows (no prices, with SKU + checkbox).
    $billing_address  = json_decode($invoice['billing_address'], true) ?: [];
    $shipping_address = json_decode($invoice['shipping_address'], true) ?: [];
    $products         = json_decode($invoice['products'], true) ?: [];

    $items_html = '';
    $total_items = 0;
    foreach ($products as $item) {
        $qty   = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $total_items += $qty;
        $sku   = trim((string)($item['sku'] ?? ''));
        $name  = (string)($item['name'] ?? $item['title'] ?? '');
        $variant = trim((string)($item['variant_title'] ?? ''));

        // Skip Shopify internal property noise but include real options as variant text.
        if ($variant === '' && !empty($item['properties']) && is_array($item['properties'])) {
            $parts = [];
            foreach ($item['properties'] as $p) {
                $pname = (string)($p['name'] ?? '');
                if ($pname === '' || $pname[0] === '_') { continue; }
                $parts[] = $pname . ': ' . ($p['value'] ?? '');
            }
            if (!empty($parts)) {
                $variant = implode(' | ', $parts);
            }
        }

        $items_html .= '<tr>';
        $items_html .= '<td>' . htmlspecialchars($name);
        if ($variant !== '') {
            $items_html .= '<span class="item-variant">' . htmlspecialchars($variant) . '</span>';
        }
        if ($sku !== '') {
            $items_html .= '<span class="item-sku">' . e('invoice.sku') . ': ' . htmlspecialchars($sku) . '</span>';
        }
        $items_html .= '</td>';
        $items_html .= '<td style="text-align: center;"><span class="qty-num">' . htmlspecialchars(fmt_number($qty, 0)) . '</span></td>';
        $items_html .= '</tr>';
    }
    if ($items_html === '') {
        $items_html = '<tr><td colspan="2" style="text-align: center; color: #777;">' . e('packing_slip.no_items') . '</td></tr>';
    }

    $replacements = [
        '{{ Company_Logo }}'    => !empty($shop_data['logo_url']) ? $shop_data['logo_url'] : '',
        '{{ Company_Name }}'    => $shop_data['store_name'] ?? '',
        '{{ Company_Address }}' => trim(
            ($shop_data['address1'] ?? '') . ' '
            . ($shop_data['address2'] ?? '') . ', '
            . ($shop_data['city'] ?? '') . ' '
            . ($shop_data['zip'] ?? '') . ', '
            . ($shop_data['country_name'] ?? ''),
            ' ,'
        ),
        '{{ Company_Phone }}'   => $shop_data['phone'] ?? '',
        '{{ Order_Number }}'    => $invoice['order_name'] ?? ('#' . ($invoice['order_number'] ?? '')),
        '{{ Invoice_Date }}'    => fmt_date($invoice['created_at'] ?? 'now', 'short'),
        '{{ Ship_Date }}'       => fmt_date(time(), 'short'),
        '{{ Shipping_Name }}'    => $shipping_address['name']     ?? ($billing_address['name']     ?? ''),
        '{{ Shipping_Address1 }}'=> $shipping_address['address1'] ?? ($billing_address['address1'] ?? ''),
        '{{ Shipping_Address2 }}'=> $shipping_address['address2'] ?? ($billing_address['address2'] ?? ''),
        '{{ Shipping_City }}'    => $shipping_address['city']     ?? ($billing_address['city']     ?? ''),
        '{{ Shipping_State }}'   => $shipping_address['province'] ?? ($billing_address['province'] ?? ''),
        '{{ Shipping_Zip }}'     => $shipping_address['zip']      ?? ($billing_address['zip']      ?? ''),
        '{{ Shipping_Country }}' => $shipping_address['country']  ?? ($billing_address['country']  ?? ''),
        '{{ Billing_Name }}'    => $billing_address['name']     ?? '',
        '{{ Billing_City }}'    => $billing_address['city']     ?? '',
        '{{ Billing_State }}'   => $billing_address['province'] ?? '',
        '{{ Billing_Zip }}'     => $billing_address['zip']      ?? '',
        '{{ Billing_Country }}' => $billing_address['country']  ?? '',
        '{{ Packing_Items }}'   => $items_html,
        '{{ Total_Items }}'     => fmt_number($total_items, 0),

        '{{ Html_Lang }}' => i18n_locale(),
        '{{ Html_Dir }}'  => i18n_dir(),

        // Translated chrome (see the invoice replacements for the rationale).
        '{{ L_Packing_Title }}' => e('packing_slip.title_display'),
        '{{ L_Order_No }}'      => e('packing_slip.order_no'),
        '{{ L_Order_Date }}'    => e('packing_slip.order_date'),
        '{{ L_Ship_Date }}'     => e('packing_slip.ship_date'),
        '{{ L_Ship_To }}'       => e('packing_slip.ship_to'),
        '{{ L_Bill_To }}'       => e('packing_slip.bill_to'),
        '{{ L_Item }}'          => e('packing_slip.item'),
        '{{ L_Qty }}'           => e('packing_slip.qty'),
        '{{ L_Total_Items }}'   => e('packing_slip.total_items', ['count' => fmt_number($total_items, 0)]),
        '{{ L_Picked_By }}'     => e('packing_slip.picked_by'),
        '{{ L_Date }}'          => e('packing_slip.date'),
        '{{ L_Note }}'          => e('packing_slip.note'),
    ];

    $template_path = __DIR__ . '/invoice_templates/html/packing-slip-1.html';
    if (!is_readable($template_path)) {
        return ['status' => 'error', 'message' => t('errors.packing_slip_template_missing')];
    }
    $template = file_get_contents($template_path);
    $html = str_replace(array_keys($replacements), array_values($replacements), $template);

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('dpi', 96);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdf_output = $dompdf->output();
    $encoded_pdf = base64_encode($pdf_output);

    DBHelper::execute(
        "UPDATE `$invoice_table`
            SET packing_slip_pdf = ?, packing_slip_status = 'generated'
          WHERE order_id = ?",
        "ss",
        [$encoded_pdf, $order_id]
    );

    return ['status' => 'success', 'message' => t('toast.packing_slip_generated')];
}

/**
 * Resolve the SMTP settings for a store: its own configuration when it has
 * saved one, otherwise the shared Sapi mailbox (free plans never configure
 * SMTP, so they always land on the fallback).
 */
function getStoreSmtpSettings($shop_data) {
    if (!empty($shop_data['smtp_settings'])) {
        return json_decode($shop_data['smtp_settings'], true);
    }

    return [
        'host' => 'mail.silverwebbuzz.com',
        'port' => '587',
        'displayname' => $shop_data['store_name'].' - Sapi',
        'username' => 'support.sapi@silverwebbuzz.com',
        'password' => 'Bhavik@1109',
        // Stores on the shared mailbox have never customised these, so they
        // get the invoice email in the store's own language.
        'subject' => i18n_default_email_subject(),
        'body' => i18n_default_email_body()
    ];
}

/**
 * Reset a subscription's usage counters when its billing period has rolled
 * over, so plan quotas (e.g. Lifetime Free = 20 invoices / 20 emails) are
 * enforced per month rather than accumulating for the life of the row.
 *
 * This is a lazy, read-time reset: it does NOT rely on Shopify renewal webhooks
 * (which reuse the same row without zeroing usage) and works for the free plan
 * too. The period is anchored on `usage_period_start`, falling back to
 * `activated_on`, then now. The anchor is advanced in whole periods so a gap in
 * traffic still lands the store back on its original billing day. Clearing the
 * notice timestamps re-arms the "limit reached" email for the new period.
 *
 * Returns the (possibly updated) plan row so callers can use fresh values.
 */
function applyMonthlyUsageReset($plan) {
    if (empty($plan) || empty($plan['id'])) {
        return $plan;
    }

    // Annual plans already carry the whole year's quota (limits are ×12), so
    // their window is the year; everything else resets every 30 days.
    $intervalDays = (isset($plan['billing_interval']) && $plan['billing_interval'] === 'annual') ? 365 : 30;
    $periodSeconds = $intervalDays * 86400;

    $anchorStr = !empty($plan['usage_period_start'])
        ? $plan['usage_period_start']
        : (!empty($plan['activated_on']) ? $plan['activated_on'] : null);

    // No anchor yet (e.g. a freshly created free row with no activated_on):
    // stamp the period start now and count from here. No reset on this pass.
    if ($anchorStr === null) {
        DBHelper::execute(
            "UPDATE store_subscriptions SET usage_period_start = NOW() WHERE id = ?",
            "i",
            [$plan['id']]
        );
        $plan['usage_period_start'] = date('Y-m-d H:i:s');
        return $plan;
    }

    $anchor = strtotime($anchorStr);
    $now = time();

    // Still inside the current period — nothing to do.
    if ($anchor === false || $now < $anchor + $periodSeconds) {
        return $plan;
    }

    // Advance the anchor by whole periods so it stays aligned to the original
    // billing day even if the app saw no traffic for several periods.
    $periodsElapsed = (int) floor(($now - $anchor) / $periodSeconds);
    $newAnchor = date('Y-m-d H:i:s', $anchor + $periodsElapsed * $periodSeconds);

    DBHelper::execute(
        "UPDATE store_subscriptions
            SET order_used = 0,
                email_used = 0,
                order_limit_notice_sent_at = NULL,
                email_limit_notice_sent_at = NULL,
                usage_period_start = ?
          WHERE id = ?",
        "si",
        [$newAnchor, $plan['id']]
    );

    $plan['order_used'] = 0;
    $plan['email_used'] = 0;
    $plan['order_limit_notice_sent_at'] = null;
    $plan['email_limit_notice_sent_at'] = null;
    $plan['usage_period_start'] = $newAnchor;

    return $plan;
}

/**
 * Tell the store owner their plan quota is exhausted and automatic invoices
 * have stopped.
 *
 * $type is 'order' (PDF quota) or 'email' (send quota). Sent at most once per
 * usage period — applyMonthlyUsageReset() clears the notice timestamp when the
 * period rolls over, which re-arms the notice for the new period.
 */
function notifyPlanLimitReached($shop_id, $type = 'order') {
    $column = ($type === 'email') ? 'email_limit_notice_sent_at' : 'order_limit_notice_sent_at';

    $plan = DBHelper::selectOne(
        "SELECT id, plan_name, price, order_limit, email_limit, `$column` AS notice_sent_at
           FROM store_subscriptions
          WHERE store_id = ? AND status = 'active'
          ORDER BY activated_on DESC LIMIT 1",
        "i",
        [$shop_id]
    );

    if (!$plan || !empty($plan['notice_sent_at'])) {
        return false;
    }

    $shop_data = DBHelper::selectOne("SELECT * FROM stores WHERE `id` = ?", "s", [$shop_id]);
    if (!$shop_data) {
        return false;
    }

    $to_email = !empty($shop_data['email_invoice']) ? $shop_data['email_invoice'] : $shop_data['email'];
    if (empty($to_email)) {
        return false;
    }

    // This one goes to the merchant, so it uses the merchant's app language.
    i18n_boot($shop_data);

    $limit = ($type === 'email') ? (int)$plan['email_limit'] : (int)$plan['order_limit'];
    $what  = ($type === 'email') ? t('limit_email.items_emails') : t('limit_email.items_invoices');
    $upgrade_url = BASE_SHOPIFY_AF_URL . 'change-plan?shop=' . urlencode($shop_data['shop']);

    $subject = t('limit_email.subject', ['plan' => $plan['plan_name']]);
    $body = '<p>' . e('limit_email.greeting', ['owner' => $shop_data['shop_owner']]) . '</p>'
        . '<p>' . t_html('limit_email.body', [
                'plan'  => $plan['plan_name'],
                'limit' => fmt_number($limit),
                'items' => $what,
          ]) . '</p>'
        . '<p>' . e('limit_email.paused') . '</p>'
        . '<p><a href="' . htmlspecialchars($upgrade_url) . '">' . e('limit_email.cta') . '</a></p>'
        . '<p>' . e('limit_email.signature') . '</p>';

    $smtp_settings = getStoreSmtpSettings($shop_data);
    $sent = sendPlainEmail($smtp_settings, $to_email, $shop_data['shop_owner'], $subject, $body);

    // Mark it sent regardless of the SMTP outcome: a failing mailbox must not
    // turn this into one notice attempt per incoming order.
    DBHelper::execute(
        "UPDATE store_subscriptions SET `$column` = NOW() WHERE id = ?",
        "i",
        [$plan['id']]
    );

    return $sent;
}

function sendemail($shop_id,$order_id, $personal_copy = false){
        
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ? ",
        "s", 
        [$shop_id]
    );

    if ($shop_data) {
        // Customer-facing email — render in the store's language.
        i18n_boot($shop_data);

        $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($shop_data['shop']));
        $invoice_table = "invoices_" . $shop_name;

        // Fetch invoice details
        $invoice = DBHelper::selectOne("SELECT * FROM `$invoice_table` WHERE order_id = ?","s", [$order_id]);

        if ($invoice) {

            // Check email limits for non-personal copies
            if (!$personal_copy) {
                $currentPlan = DBHelper::selectOne(
                    "SELECT * FROM store_subscriptions WHERE store_id = ? AND status = 'active' ORDER BY activated_on DESC LIMIT 1",
                    "i",
                    [$shop_id]
                );
                $currentPlan = applyMonthlyUsageReset($currentPlan);

                if ($currentPlan && $currentPlan['email_used'] >= $currentPlan['email_limit']) {
                    return json_encode([
                        'status' => 'error',
                        'message' => t('errors.email_limit_reached')
                    ]);
                }
            }
            
            $decoded_pdf = base64_decode($invoice['pdf_invoice']);
            $billing_address = json_decode($invoice['billing_address'], true);

            $smtp_settings = getStoreSmtpSettings($shop_data);

            if ($personal_copy) {
                $to_email = $shop_data['email_invoice'] ?? $shop_data['email'];
                $to_name = $shop_data['shop_owner'];
                $subject = t('invoice_email.store_copy_prefix') . ' ' . str_replace(['{invoice_number}','{shop_name}'],[$invoice['order_name'],$shop_data['store_name']],$smtp_settings['subject']);
            } else {
                $to_email = $invoice['customer_email'];
                $to_name = $invoice['customer_name'];
                $subject = str_replace(['{invoice_number}','{shop_name}'],[$invoice['order_name'],$shop_data['store_name']],$smtp_settings['subject']);
            }
            // Guest checkouts (and payloads with no customer object) can leave
            // the invoice with no address at all — PHPMailer would just throw
            // and the merchant would see a bare "failed".
            if (trim((string)$to_email) === '') {
                if (!$personal_copy) {
                    DBHelper::execute(
                        "UPDATE `$invoice_table` SET email_status = 'failed' WHERE order_id = ? ",
                        "s",
                        [$order_id]
                    );
                }
                return json_encode([
                    'status' => 'error',
                    'message' => $personal_copy ? t('toast.email_owner_failed') : t('errors.no_recipient_email'),
                    'email_status' => 'failed'
                ]);
            }

            $body = $smtp_settings['body'];
            //When sending an email, you would replace the variables like this:
            $email_body = str_replace(
                ['{invoice_number}', '{customer_name}', '{total_price}', '{currency}', '{created_at}'],
                [$invoice['order_name'], $invoice['customer_name'], $invoice['total_price'], $invoice['currency'], $invoice['created_at']],
                $body
            );
            
            // Send email with attachment
            $email_sent = sendEmailWithAttachment($smtp_settings, $to_email,$to_name, $subject, $email_body, $decoded_pdf, "invoice_{$invoice['order_name']}.pdf");

            $email_status = $email_sent ? 'sent' : 'failed';

            // Update email status and increment count only for customer emails
            if (!$personal_copy) {
                // Update email status in invoice table
                $affectedRows = DBHelper::execute(
                    "UPDATE `$invoice_table` SET email_status = ? WHERE order_id = ? ",
                    "ss",
                    [$email_status, $order_id]
                );
                
                // Increment email usage count
                $affectedRows = DBHelper::execute(
                    "UPDATE `store_subscriptions` SET email_used = email_used+1 WHERE store_id = ? ",
                    "s",
                    [$shop_id]
                );
            }

            // A failed send is an error, not a success with a sad message —
            // script.js switches the toast colour on `status`.
            return json_encode([
                'status' => $email_sent ? 'success' : 'error',
                'message' => $personal_copy
                    ? ($email_sent ? t('toast.email_owner_sent') : t('toast.email_owner_failed'))
                    : ($email_sent ? t('toast.email_sent') : t('toast.email_failed')),
                'email_status' => $email_status
            ]);
        } else {
            return json_encode([
                'status' => 'error',
                'message' => t('errors.invoice_not_found')
            ]);
        }
    } else {
        return json_encode([
            'status' => 'error',
            'message' => t('errors.shop_not_found')
        ]);
    }
}

// Notification email without an invoice attached (plan limit notices, etc).
function sendPlainEmail($smtp_settings, $to_email, $to_name, $subject, $html_body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtp_settings['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_settings['username'];
        $mail->Password   = $smtp_settings['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp_settings['port'];
        // An unresponsive mail host must not hold the worker open indefinitely;
        // this runs after the webhook has already answered Shopify.
        $mail->Timeout    = 20;

        $from_email = !empty($smtp_settings['from_email']) ? $smtp_settings['from_email'] : $smtp_settings['username'];
        $mail->setFrom($from_email, $smtp_settings['displayname'], true);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags($html_body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error (plain): " . $e->getMessage());
        return false;
    }
}

// Email sending function
function sendEmailWithAttachment($smtp_settings, $to_email, $to_name, $subject, $html_body, $attachment_content, $attachment_name) {
    
    $mail = new PHPMailer(true);
 
     try {
         // Server settings
         //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
         $mail->isSMTP();
         $mail->Host       = $smtp_settings['host'];
         $mail->SMTPAuth   = true;
         $mail->Username   = $smtp_settings['username'];
         $mail->Password   = $smtp_settings['password'];
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
         $mail->Port       = $smtp_settings['port'];
         $mail->SMTPKeepAlive = true;
         $mail->Timeout    = 20;
 
         // Critical headers
         // Use from_email if set, otherwise fallback to username
         $from_email = !empty($smtp_settings['from_email']) ? $smtp_settings['from_email'] : $smtp_settings['username'];
         $mail->setFrom($from_email, $smtp_settings['displayname'], true);
         //$mail->addReplyTo('support@silverwebbuzz.com', 'Support Team');
         //$mail->addAddress('vishnu@silverwebbuzz.com', 'Vishnu Prajapati');
         $mail->addAddress($to_email, $to_name);
         // Add BCC for monitoring
         $mail->addBCC('bhavik.koradiya@silverwebbuzz.com', 'Bhavik Koradiya');
 
         // Content
         $mail->isHTML(true);
         $mail->Subject = $subject;
         $mail->Body = $html_body;
         $mail->AltBody = strip_tags($html_body);
         
         // Add PDF attachment from string
         $mail->addStringAttachment($attachment_content, $attachment_name, 'base64', 'application/pdf');
             
         // Delivery notifications
         //$mail->addCustomHeader('Return-Receipt-To: bhavik.koradiya@silverwebbuzz.com');
         //$mail->addCustomHeader('Disposition-Notification-To: bhavik.koradiya@silverwebbuzz.com');
         
         // Send with verification
         if (!$mail->send()) {
             throw new Exception('Send failed: ' . $mail->ErrorInfo);
         }
         return true;
 
     } catch (Exception $e) {
         // Never echo — sendemail.php returns JSON to the admin UI, and the
         // webhook must not emit a body before its 200.
         error_log("Mail Error: " . $e->getMessage());
         return false;
         
     }
 }



/**
 * Persist an app_subscriptions/update webhook payload into store_subscriptions.
 *
 * The full payload from Shopify already contains everything we need
 * (name, status, price, interval, created_at, currency), so we use it
 * directly instead of making a separate GraphQL round-trip that may
 * race with Shopify's own propagation.
 *
 * Behaviour:
 *  - UPSERT on charge_id (unique key) so re-deliveries are idempotent
 *  - When a new ACTIVE subscription lands, any other active row for
 *    the same store is moved to 'cancelled'
 */
function subscription_log($label, $data = null) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $label;
    if ($data !== null) {
        $line .= ' :: ' . (is_scalar($data) ? var_export($data, true) : json_encode($data, JSON_UNESCAPED_SLASHES));
    }
    error_log($line . "\n", 3, __DIR__ . '/webhook_debug.log');
}

function store_app_subscriptions($shopId, array $subscription)
{
    subscription_log('store_app_subscriptions called', ['shopId' => $shopId, 'payload' => $subscription]);

    // shopify_id is bigint — bind as string to avoid 32-bit int truncation
    $store = DBHelper::selectOne(
        "SELECT id, shop, access_token FROM stores WHERE shopify_id = ? LIMIT 1",
        "s",
        [(string)$shopId]
    );
    if (!$store) {
        subscription_log('STORE NOT FOUND', $shopId);
        throw new Exception("Store not found for shopify_id $shopId");
    }
    subscription_log('store found', ['internal_id' => $store['id']]);

    $chargeId = extractIdFromGql($subscription['admin_graphql_api_id'] ?? '');
    if (!$chargeId) {
        throw new Exception("Missing charge id in webhook payload");
    }

    $planName        = $subscription['name'] ?? '';
    $status          = strtolower($subscription['status'] ?? 'active');
    $price           = $subscription['price'] ?? 0;
    $currency        = $subscription['currency'] ?? 'USD';
    $rawInterval     = strtolower($subscription['interval'] ?? '');
    $isAnnual        = ($rawInterval === 'annual');
    $billingInterval = $isAnnual ? 'annual' : 'every_30_days';
    $isTest          = !empty($subscription['test']) ? 1 : 0;

    $activatedOn = !empty($subscription['created_at'])
        ? date('Y-m-d H:i:s', strtotime($subscription['created_at']))
        : date('Y-m-d H:i:s');

    // Shopify is the source of truth for billing dates. The webhook payload
    // omits current_period_end and trial_ends_on, so look them up via GraphQL
    // on the active subscription only. Skip the API hit on cancelled/expired.
    $currentPeriodEnd = null;
    $trialEndsOn      = null;
    if ($status === 'active' && !empty($store['shop']) && !empty($store['access_token'])) {
        $billing = fetchSubscriptionBillingDates($store['shop'], $store['access_token'], $chargeId);
        subscription_log('billing dates from shopify', $billing);
        if ($billing) {
            $currentPeriodEnd = !empty($billing['current_period_end'])
                ? date('Y-m-d H:i:s', strtotime($billing['current_period_end']))
                : null;
            $trialEndsOn = !empty($billing['trial_ends_on'])
                ? date('Y-m-d H:i:s', strtotime($billing['trial_ends_on']))
                : null;
        }
    }

    $limits = calculatePlanLimits($planName, (float)$price, $isAnnual ? 'annual' : 'monthly');

    $row = [
        'store_id'           => (int)$store['id'],
        'shopify_id'         => (string)$shopId,
        'charge_id'          => (string)$chargeId,
        'plan_name'          => $planName,
        'status'             => $status,
        'price'              => $price,
        'currency'           => $currency,
        'billing_interval'   => $billingInterval,
        'activated_on'       => $activatedOn,
        'current_period_end' => $currentPeriodEnd,
        'trial_ends_on'      => $trialEndsOn,
        'order_limit'        => (int)$limits['order_limit'],
        'email_limit'        => (int)$limits['email_limit'],
        'is_test'            => $isTest,
    ];
    subscription_log('upserting', $row);

    // UPSERT on charge_id (UNIQUE KEY unique_charge). All bigint columns are
    // bound as strings (`s`) to avoid 32-bit PHP int truncation on values
    // like shopify_id=105544581452.
    $sql = "
        INSERT INTO store_subscriptions
            (store_id, shopify_id, charge_id, plan_name, status, price, currency,
             billing_interval, interval_count, activated_on, current_period_end, trial_ends_on,
             order_limit, email_limit, is_test)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            plan_name          = VALUES(plan_name),
            status             = VALUES(status),
            price              = VALUES(price),
            currency           = VALUES(currency),
            billing_interval   = VALUES(billing_interval),
            activated_on       = VALUES(activated_on),
            current_period_end = VALUES(current_period_end),
            trial_ends_on      = VALUES(trial_ends_on),
            order_limit        = VALUES(order_limit),
            email_limit        = VALUES(email_limit),
            is_test            = VALUES(is_test),
            updated_at         = NOW()
    ";

    DBHelper::insert(
        $sql,
        "isssssssisssiii",
        [
            $row['store_id'],
            $row['shopify_id'],
            $row['charge_id'],
            $row['plan_name'],
            $row['status'],
            $row['price'],
            $row['currency'],
            $row['billing_interval'],
            1,                        // interval_count
            $row['activated_on'],
            $row['current_period_end'],
            $row['trial_ends_on'],
            $row['order_limit'],
            $row['email_limit'],
            $row['is_test'],
        ]
    );
    subscription_log('upsert ok');

    // When a new active subscription arrives, cancel any other active rows
    // for the same store (handles plan upgrades — old Growth -> new Premium,
    // and clears the Lifetime Free row created at install time).
    if ($status === 'active') {
        DBHelper::execute(
            "UPDATE store_subscriptions
                SET status = 'cancelled', cancelled_on = NOW()
              WHERE shopify_id = ?
                AND (charge_id IS NULL OR charge_id != ?)
                AND status = 'active'",
            "ss",
            [(string)$shopId, (string)$chargeId]
        );
        subscription_log('cancelled previous active rows for shopify_id', $shopId);
    }
}
?>