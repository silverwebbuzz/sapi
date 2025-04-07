<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config.php'; 
require_once '../db.php';
// Database connection
$conn = DB::getInstance();

// Get current shop ID (you'll need to set this based on your authentication)
if(isset($_GET['shop']))
{
    $_SESSION['shop_id'] = $shop_id = $_GET['shop_id']; 
}
else
{
    $shop_id = $_SESSION['shop_id'];
}
    // This should come from your session/auth system

    //$store['shop'] = 'silverwebbuzzapp.myshopify.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWB Auto PDF Invoices</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="main-header">
            <h1>SWB Auto PDF Invoices</h1>
            <div class="user-profile">
                <span>Admin</span>
                <div class="avatar">A</div>
            </div>
        </header>