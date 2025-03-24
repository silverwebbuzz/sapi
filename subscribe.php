<?php
require_once 'db.php';

$store_id = 1; // Assume the store is logged in
$plan_id = $_POST['plan_id'];

$conn = DB::getInstance();

// Check if store already has a subscription
$stmt = $conn->prepare("SELECT id FROM store_subscriptions WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing subscription
    $update = $conn->prepare("UPDATE store_subscriptions SET plan_id = ?, start_date = NOW(), end_date = DATE_ADD(NOW(), INTERVAL 7 DAY), status = 'active' WHERE store_id = ?");
    $update->bind_param("ii", $plan_id, $store_id);
    $update->execute();
} else {
    // Create a new subscription
    $insert = $conn->prepare("INSERT INTO store_subscriptions (store_id, plan_id, start_date, end_date, status) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active')");
    $insert->bind_param("ii", $store_id, $plan_id);
    $insert->execute();
}

// Redirect back to pricing page
header("Location: pricing.php?success=1");
exit();
