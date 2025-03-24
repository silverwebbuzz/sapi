<?php
require_once 'config.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Invalid request method.");
}

// Retrieve GET parameters
$shop_id = $_GET['shop_id'];
$plan_id = $_GET['plan_id'];

$conn = DB::getInstance();

// Check if store already has an active subscription
$stmt = $conn->prepare("SELECT id FROM store_subscriptions WHERE store_id = ? AND status = 'active'");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$subscription_result = $stmt->get_result();

if ($subscription_result->num_rows > 0) {
    // Store is already subscribed
    header("Location: dashboard.php?shop_id=$shop_id");
    exit();
} else {
    // Subscribe the store (7-day free trial)
    $stmt = $conn->prepare("
        INSERT INTO store_subscriptions (store_id, plan_id, start_date, end_date, status) 
        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active')
    ");
    $stmt->bind_param("ii", $shop_id, $plan_id);

    if ($stmt->execute()) {
        // Redirect to dashboard after successful subscription
        header("Location: dashboard?shop_id=$shop_id");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>