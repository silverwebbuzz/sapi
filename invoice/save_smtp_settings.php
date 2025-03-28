<?php
 require_once '../config.php'; 
 require_once '../db.php';

 // Database connection
 $conn = DB::getInstance();
 
$shop_id = 1; // Should come from your session/auth system

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Validate inputs
    $required = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'email_subject', 'email_body'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $_SESSION['error'] = "All fields are required";
            header('Location: settings.php#email');
            exit;
        }
    }

    // Prepare settings
    $smtp_settings = [
        'host' => htmlspecialchars($_POST['smtp_host']),
        'port' => (int)$_POST['smtp_port'],
        'username' => htmlspecialchars($_POST['smtp_user']),
        'password' => $_POST['smtp_pass'],
        'subject' => htmlspecialchars($_POST['email_subject']),
        'body' => sanitizeHtml($_POST['email_body']) // HTML purifier
    ];

    // Update database
    $json_settings = json_encode($smtp_settings);
    $stmt = $conn->prepare("UPDATE stores SET smtp_settings = ? WHERE id = ?");
    $stmt->bind_param("ss", $json_settings, $shop_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "SMTP settings updated successfully"; // Changed to success_message
    } else {
        $_SESSION['error_message'] = "Failed to update settings: " . htmlspecialchars($conn->error); // Changed to error_message and added htmlspecialchars
    }
    
    header('Location: settings.php#email');
    exit;
}

// Simple HTML Purifier Alternative (basic sanitization)
function sanitizeHtml($html) {
    $allowed_tags = '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    return strip_tags($html, $allowed_tags);
}