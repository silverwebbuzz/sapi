<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'shopify_functions.php';
require_once '../vendor/autoload.php';
 
$data = '{"app_subscription":{"admin_graphql_api_id":"gid:\/\/shopify\/AppSubscription\/34989998380","name":"Starter","status":"ACTIVE","admin_graphql_api_shop_id":"gid:\/\/shopify\/Shop\/92496724268","created_at":"2025-04-16T07:20:12-07:00","updated_at":"2025-04-16T07:20:14-07:00","currency":"USD","capped_amount":null}}';
$webhook = json_decode($data, true);
try {
        $subscription = $webhook['app_subscription'];
        $chargeId = extractIdFromGql($subscription['admin_graphql_api_id']);  // Returns 34950971692
        $shopId = extractIdFromGql($subscription['admin_graphql_api_shop_id']);  // Returns 92496724
        
        // Get store data in shopify table its save as 	shopify_id
        $store = DBHelper::selectOne("SELECT id, shop, access_token FROM stores WHERE shopify_id = ?  LIMIT 1", "i", [$shopId]);
        
        if (!$store) {
            throw new Exception("Store not found");
        }
        
        // Get FULL subscription details via GraphQL
        $subscriptionData = fetchSubscriptionWithGraphQL($store['shop'],$store['access_token'],$chargeId);
        
        if (!$subscriptionData) {
            throw new Exception("Failed to fetch subscription details");
        }

        // Determine plan limits based on your requirements
        $limits = calculatePlanLimits($subscriptionData['name'], $subscriptionData['price'], $subscriptionData['billing_interval']);
        
        // Clean up optional fields if not set
        $trialEndsOn = !empty($subscriptionData['trial_ends_on']) ? $subscriptionData['trial_ends_on'] : null;
        $cappedAmount = !empty($subscriptionData['capped_amount']) ? $subscriptionData['capped_amount'] : null;
        $terms = !empty($subscriptionData['terms']) ? $subscriptionData['terms'] : null;

        // Insert new subscription
        $newId = DBHelper::insert("
            INSERT INTO store_subscriptions (
                store_id, shopify_id, charge_id, initial_charge_id,
                plan_name, status, price, currency, billing_interval,
                interval_count, capped_amount, terms,
                activated_on, current_period_end, trial_ends_on, billing_on,
                order_limit, email_limit, order_used, email_used,
                is_test
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", "iisssssdssisssssiiii", [
            $store['id'],                             // store_id
            $shopId,                                  // shop_id
            $subscriptionData['id'],                  // charge_id
            $subscriptionData['id'],                  // initial_charge_id (same for new subs)
            $subscriptionData['name'],                // plan_name
            strtolower($subscriptionData['status']),  // status (ensure lowercase)
            $subscriptionData['price'],               // price
            $subscriptionData['currency'],            // currency
            $subscriptionData['billing_interval'],    // billing_interval
            $subscriptionData['interval_count'],      // interval_count
            $cappedAmount,                            // capped_amount (can be null)
            $terms,                                   // terms (can be null)
            $subscriptionData['activated_on'],        // activated_on
            $subscriptionData['current_period_end'],  // current_period_end
            $trialEndsOn,                             // trial_ends_on (null if missing)
            null,                                     // billing_on (to update after first billing cycle)
            $limits['order_limit'],                   // order_limit
            $limits['email_limit'],                   // email_limit
            0,                                        // order_used
            0,                                        // email_used
            $subscriptionData['is_test']              // is_test
        ]);
        
        // 8. Cancel old subscriptions (except the one we just created)
        DBHelper::execute("
            UPDATE store_subscriptions 
            SET status = 'cancelled',
                cancelled_on = NOW(),
                updated_at = NOW()
            WHERE shopify_id = ? 
              AND id != ?
              AND status = 'active'
        ", "ii", [$shopId, $newId]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'subscription_id' => $newId,
            'limits' => $limits
        ]);
        
    } catch (Exception $e) {
        http_response_code(200);
        error_log("Subscription processing failed: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }