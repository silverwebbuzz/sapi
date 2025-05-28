<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die("Invalid request method.");
}
// Retrieve GET parameters
$shop_id = $_GET['shop_id'] ?? 0;
$order_id = $_GET['order_id'] ?? 0;
$email_status = $_GET['emailstatus'] ?? 'pending';
$personal_copy = isset($_GET['personal_copy']) && $_GET['personal_copy'] === 'true';

if ($shop_id && $order_id) {
    $result = sendemail($shop_id, $order_id, $personal_copy);
    echo $result;
} else {
    echo "Invalid parameters";
}
exit;
?>