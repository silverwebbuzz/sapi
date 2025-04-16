<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'shopify_functions.php';
require_once '../vendor/autoload.php';
 
$data = '{"app_subscription":{"admin_graphql_api_id":"gid:\/\/shopify\/AppSubscription\/34952020268","name":"Lifetime Free","status":"CANCELLED","admin_graphql_api_shop_id":"gid:\/\/shopify\/Shop\/92496724268","created_at":"2025-04-16T06:47:22-07:00","updated_at":"2025-04-16T07:20:14-07:00","currency":"USD","capped_amount":null}}';
$webhook = json_decode($data, true);
try {
        $subscription = $webhook['app_subscription'];
        $chargeId = extractIdFromGql($subscription['admin_graphql_api_id']);  // Returns 34950971692
        $shopId = extractIdFromGql($subscription['admin_graphql_api_shop_id']);  // Returns 92496724
        
        // Get store data in shopify table its save as 	shopify_id
        $store = DBHelper::fetch("SELECT id, shop, access_token FROM stores WHERE shopify_id = ?  LIMIT 1", "i", [$shopId]);
        
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

        // Start transaction
        DBHelper::beginTransaction();
        
        // Insert new subscription
        $newId = DBHelper::insert("
            INSERT INTO store_subscriptions (
                store_id, shop_id, shop_domain, charge_id, initial_charge_id,
                plan_name, status, price, currency, billing_interval,
                interval_count, capped_amount, terms,
                activated_on, current_period_end, trial_ends_on, billing_on,
                order_limit, email_limit, order_used, email_used,
                is_test
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", "iissssssdssisssssiiii", [
            $store['id'],
            $shopId,
            $store['myshopify_domain'],
            $chargeId,
            $chargeId, // Initial charge same as current for new subscriptions
            $subscriptionData['name'],
            $subscriptionData['status'],
            $subscriptionData['price'],
            $subscriptionData['currency'],
            $subscriptionData['billing_interval'],
            $subscriptionData['interval_count'],
            $subscriptionData['capped_amount'],
            $subscriptionData['terms'],
            $subscriptionData['activated_on'],
            $subscriptionData['current_period_end'],
            $subscriptionData['trial_ends_on'],
            null, // billing_on will be set after first charge
            $limits['order_limit'],
            $limits['email_limit'],
            0, // order_used
            0, // email_used
            $subscriptionData['is_test']
        ]);
        
        // 8. Cancel old subscriptions (except the one we just created)
        DBHelper::execute("
            UPDATE store_subscriptions 
            SET status = 'cancelled',
                cancelled_on = NOW(),
                updated_at = NOW()
            WHERE store_id = ? 
              AND id != ?
              AND status = 'active'
        ", "ii", [$store['id'], $newId]);
        
        DBHelper::commit();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'subscription_id' => $newId,
            'limits' => $limits
        ]);
        
    } catch (Exception $e) {
        DBHelper::rollback();
        http_response_code(200);
        error_log("Subscription processing failed: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
