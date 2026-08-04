<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'i18n.php';
require_once '../vendor/autoload.php';

// sendemail() re-boots with the store's locale once it has the row; this
// covers the error paths that return before that happens.
i18n_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(t('errors.invalid_request_method'));
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
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => t('errors.invalid_parameters')]);
}
exit;
?>