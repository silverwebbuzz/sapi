<?php
require_once '../config.php';
require_once '../db.php';

// Verify webhook HMAC
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
$data = file_get_contents('php://input');
$calculated_hmac = base64_encode(hash_hmac('sha256', $data, SHOPIFY_API_SECRET, true));

if (!hash_equals($hmac, $calculated_hmac)) {
    http_response_code(401);
    die('Invalid webhook HMAC');
}

// Process order
$order = json_decode($data, true);
$conn = DB::getInstance();
$stmt = $conn->prepare("INSERT INTO invoice_test (Param, status) VALUES (?, ?)");

// Bind parameters
$status = '1'; // Default status
$stmt->bind_param("ss", $data, $status);

// Execute the query
if ($stmt->execute()) {
    error_log("Data inserted into invoice_test successfully!");
} else {
    error_log("Error inserting into invoice_test: " . $stmt->error);
}

http_response_code(200);