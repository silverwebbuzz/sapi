<?php
// Import Dom PDF classes
use Dompdf\Dompdf;
use Dompdf\Options;

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function generatepdf($shop_id,$order_id){
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ? ",
        "s", 
        [$shop_id]
    );
    
    if ($shop_data) {
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
            $tax_label = '';
            $tax_rate = null;

            foreach ($products as $item) {
                $tax_amount = 0.0;
                $item_tax_rate = null;
                $item_tax_title = '';

                if (isset($item['tax_lines']) && !empty($item['tax_lines'])) {
                    $item_tax_rate = $item['tax_lines'][0]['rate'];
                    $tax_amount = floatval($item['tax_lines'][0]['price']);
                    $item_tax_title = $item['tax_lines'][0]['title'] ?? '';
                    if ($tax_label === '' && !empty($item_tax_title)) {
                        $tax_label = $item_tax_title;
                        $tax_rate = $item_tax_rate;
                    }
                }

                $unit_price = isset($item['price_set']['shop_money']['amount']) ? floatval($item['price_set']['shop_money']['amount']) : floatval($item['price']);
                $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 1.0;
                $line_total_gross = $unit_price * $quantity;
                // Shopify's `price` is tax-inclusive; derive ex-tax from the rate so that
                // line-level discounts (which Shopify applies to tax_lines.price) don't
                // contaminate the unit ex-tax figure.
                if ($item_tax_rate !== null && $item_tax_rate > 0) {
                    $unit_price_ex_tax = $unit_price / (1 + floatval($item_tax_rate));
                } elseif ($tax_amount > 0) {
                    $unit_price_ex_tax = max(0, ($line_total_gross - $tax_amount) / max(1.0, $quantity));
                } else {
                    $unit_price_ex_tax = $unit_price;
                }
                $line_total_ex_tax = $unit_price_ex_tax * $quantity;
                $subtotal_ex_tax += $line_total_ex_tax;
                $item_tax_total += $tax_amount;

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

                $row_tax_text = $item_tax_rate !== null ? round($item_tax_rate * 100, 2) . '%' : '';
                $items_html .= '<tr>';
                $items_html .= '<td>'.htmlspecialchars($item['name']).'</td>';
                $items_html .= '<td>'.htmlspecialchars($description).'</td>';
                $items_html .= '<td class="num">'.$invoice['currency'].' '.number_format($unit_price_ex_tax, 2).'</td>';
                $items_html .= '<td class="qty">'.number_format($quantity, 0).'</td>';
                $items_html .= '<td>'.htmlspecialchars($row_tax_text).'</td>';
                $items_html .= '<td class="num">'.$invoice['currency'].' '.number_format($line_total_ex_tax, 2).'</td>';
                $items_html .= '</tr>';
                $counter++;
            }

            $shipping_tax_amount = floatval($invoice['tax_amount']) - $item_tax_total;
            if ($shipping_tax_amount < 0) {
                $shipping_tax_amount = 0;
            }

            $has_tax = floatval($invoice['tax_amount']) > 0;
            if ($tax_label === '') {
                $tax_label = 'Tax';
            }
            $tax_rate_text = $tax_rate !== null ? ' (' . round($tax_rate * 100, 2) . '%)' : '';
            $tax_label_full = $has_tax ? $tax_label . $tax_rate_text : '';
            if ($has_tax) {
                $lc_tax_label = strtolower($tax_label);
                if (strpos($lc_tax_label, 'vat') !== false) {
                    $shipping_tax_label = 'Shipping VAT' . $tax_rate_text;
                } elseif (strpos($lc_tax_label, 'gst') !== false) {
                    $shipping_tax_label = 'Shipping GST' . $tax_rate_text;
                } else {
                    $shipping_tax_label = 'Shipping ' . $tax_label . $tax_rate_text;
                }
            } else {
                $shipping_tax_label = '';
            }
            $tax_amount_display = $has_tax ? $invoice['currency'].' '.number_format($invoice['tax_amount'], 2) : '';

            $discount_row = '';
            if (floatval($invoice['discount_amount']) != 0) {
                $discount_amount = abs(floatval($invoice['discount_amount']));
                $discount_row = '<tr><td class="label">Discount</td><td class="value">-' . $invoice['currency'] . ' ' . number_format($discount_amount, 2) . '</td></tr>';
            }

            $shipping_tax_block = '';
            if ($shipping_tax_amount > 0) {
                $shipping_tax_block = '<tr><td class="label">' . htmlspecialchars($shipping_tax_label) . '</td><td class="value">' . $invoice['currency'] . ' ' . number_format($shipping_tax_amount, 2) . '</td></tr>';
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
                '{{ Invoice_Date }}' => date('d/m/Y', strtotime($invoice['created_at'])),
                '{{ Due_Date }}' => date('d/m/Y', strtotime($invoice['created_at'].' +15 days')),
                '{{ Billing_Name }}' => $billing_address['name'] ?? '',
                '{{ Billing_Address1 }}' => $billing_address['address1'] ?? '',
                '{{ Billing_Address2 }}' => $billing_address['address2'] ?? '',
                '{{ Billing_City }}' => $billing_address['city'] ?? '',
                '{{ Billing_State }}' => $billing_address['province'] ?? '',
                '{{ Billing_Zip }}' => $billing_address['zip'] ?? '',
                '{{ Billing_Country }}' => $billing_address['country'] ?? '',
                '{{ Billing_GSTIN }}' => '', // Add GSTIN if available
                '{{ Billing_Email }}' => $invoice['customer_email'] ?? '',
                '{{ Billing_Phone }}' => $billing_address['phone'] ?? 'No phone number',
                '{{ Shipping_Name }}' => isset($shipping_address['name']) ? $shipping_address['name'] : ($billing_address['name'] ?? ''),
                '{{ Shipping_Address1 }}' => isset($shipping_address['address1']) ? $shipping_address['address1'] : ($billing_address['address1'] ?? ''),
                '{{ Shipping_Address2 }}' => isset($shipping_address['address2']) ? $shipping_address['address2'] : ($billing_address['address2'] ?? ''),
                '{{ Shipping_City }}' => isset($shipping_address['city']) ? $shipping_address['city'] : ($billing_address['city'] ?? ''),
                '{{ Shipping_State }}' => isset($shipping_address['province']) ? $shipping_address['province'] : ($billing_address['province'] ?? ''),
                '{{ Shipping_Zip }}' => isset($shipping_address['zip']) ? $shipping_address['zip'] : ($billing_address['zip'] ?? ''),
                '{{ Shipping_Country }}' => isset($shipping_address['country']) ? $shipping_address['country'] : ($billing_address['country'] ?? ''),
                '{{ Order_Items }}' => $items_html,
                '{{ Subtotal }}' => $invoice['currency'].' '.number_format($subtotal_ex_tax, 2),
                '{{ Tax_Column_Label }}' => htmlspecialchars($tax_label_full),
                '{{ Tax_Label }}' => htmlspecialchars($tax_label_full),
                '{{ Tax_Amount }}' => $tax_amount_display,
                '{{ Shipping_Cost }}' => $invoice['currency'].' '.number_format($invoice['shipping_cost'], 2),
                '{{ Shipping_Tax_Block }}' => $shipping_tax_block,
                '{{ Discount_Block }}' => $discount_row,
                '{{ Total_Amount }}' => $invoice['currency'].' '.number_format($invoice['total_price'], 2),
                '{{ Payment_Method }}' => $invoice['payment_method'] ?? 'Unknown',
                '{{ Payment_Status }}' => ucfirst($invoice['order_status'])
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
            return "PDF Generated Successfully.";

        } else {
            return "No invoice found with the specified order ID.";
        }
    } else {
        return "No shop found with the specified ID.";
    }
}

function sendemail($shop_id,$order_id, $personal_copy = false){
        
    $shop_data = DBHelper::selectOne(
        "SELECT * FROM stores WHERE `id` = ? ",
        "s", 
        [$shop_id]
    );

    if ($shop_data) {
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
                
                if ($currentPlan['email_used'] >= $currentPlan['email_limit']) {
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Email limit reached. Please upgrade your plan to send more emails.'
                    ]);
                }
            }
            
            $decoded_pdf = base64_decode($invoice['pdf_invoice']);
            $billing_address = json_decode($invoice['billing_address'], true);

            // SMTP settings 
            if($shop_data['smtp_settings']!=''){
                $smtp_settings = json_decode($shop_data['smtp_settings'], true);
            }else{
                $default_subject = DEFAULT_EMAIL_SUBJECT;
                $default_body = DEFAULT_EMAIL_BODY;
                $smtp_settings = [
                    'host' => 'mail.silverwebbuzz.com',
                    'port' => '587',
                    'displayname' => $shop_data['store_name'].' - Sapi',
                    'username' => 'support.sapi@silverwebbuzz.com',
                    'password' => 'Bhavik@1109',
                    'subject' => $default_subject,
                    'body' => $default_body
                ];
            }
            
            if ($personal_copy) {
                $to_email = $shop_data['email_invoice'] ?? $shop_data['email'];
                $to_name = $shop_data['shop_owner'];
                $subject = "[Store Copy] " . str_replace(['{invoice_number}','{shop_name}'],[$invoice['order_name'],$shop_data['store_name']],$smtp_settings['subject']);
            } else {
                $to_email = $invoice['customer_email'];
                $to_name = $invoice['customer_name'];
                $subject = str_replace(['{invoice_number}','{shop_name}'],[$invoice['order_name'],$shop_data['store_name']],$smtp_settings['subject']);
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

            return json_encode([
                'status' => 'success',
                'message' => $personal_copy ? 
                    ($email_sent ? 'Store copy sent successfully.' : 'Failed to send store copy.') :
                    ($email_sent ? 'Email sent successfully.' : 'Failed to send email.'),
                'email_status' => $email_status
            ]);
        } else {
            return json_encode([
                'status' => 'error',
                'message' => 'No invoice found with the specified order ID.'
            ]);
        }
    } else {
        return json_encode([
            'status' => 'error',
            'message' => 'No shop found with the specified ID.'
        ]);
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
         echo "Error: " . $e->getMessage();
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
        "SELECT id FROM stores WHERE shopify_id = ? LIMIT 1",
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

    $limits = calculatePlanLimits($planName, (float)$price, $isAnnual ? 'annual' : 'monthly');

    $row = [
        'store_id'         => (int)$store['id'],
        'shopify_id'       => (string)$shopId,
        'charge_id'        => (string)$chargeId,
        'plan_name'        => $planName,
        'status'           => $status,
        'price'            => $price,
        'currency'         => $currency,
        'billing_interval' => $billingInterval,
        'activated_on'     => $activatedOn,
        'order_limit'      => (int)$limits['order_limit'],
        'email_limit'      => (int)$limits['email_limit'],
        'is_test'          => $isTest,
    ];
    subscription_log('upserting', $row);

    // UPSERT on charge_id (UNIQUE KEY unique_charge). All bigint columns are
    // bound as strings (`s`) to avoid 32-bit PHP int truncation on values
    // like shopify_id=105544581452.
    $sql = "
        INSERT INTO store_subscriptions
            (store_id, shopify_id, charge_id, plan_name, status, price, currency,
             billing_interval, interval_count, activated_on,
             order_limit, email_limit, is_test)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            plan_name        = VALUES(plan_name),
            status           = VALUES(status),
            price            = VALUES(price),
            currency         = VALUES(currency),
            billing_interval = VALUES(billing_interval),
            order_limit      = VALUES(order_limit),
            email_limit      = VALUES(email_limit),
            is_test          = VALUES(is_test),
            updated_at       = NOW()
    ";

    DBHelper::insert(
        $sql,
        "isssssssisiii",
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