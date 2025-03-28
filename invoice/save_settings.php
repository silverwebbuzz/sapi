<?php header('Content-Type: application/json');

 require_once '../config.php'; 
 require_once '../db.php';
 // Database connection
 $conn = DB::getInstance();
 
$shop_id = 1; // Should come from your session/auth system

$response = ['success' => false, 'message' => ''];

try {
    $section = $_POST['section'] ?? '';
    
    if ($section === 'general') {
        // Prepare general settings
        $auto_invoice_customer = isset($_POST['auto_invoice_customer']) ? 'Yes' : 'No';
        $auto_invoice_personal = isset($_POST['auto_invoice_personal']) ? 'Yes' : 'No';
        $email_invoice = $_POST['email_invoice'] ?? '';
        
        // Update database
        $stmt = $conn->prepare("UPDATE stores SET auto_invoice_customer = ?, auto_invoice_personal = ?, email_invoice = ? WHERE id = ?");
        $stmt->bind_param("ssss",  $auto_invoice_customer, $auto_invoice_personal, $email_invoice, $shop_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'General settings saved successfully!';
        } else {
            $response['message'] = 'Failed to save general settings';
        }
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);