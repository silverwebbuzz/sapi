<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../config/config.php';
require_once '../config/db.php';
require_once 'helper.php';
require_once 'i18n.php';
require_once '../vendor/autoload.php';

// generatepdf() re-boots with the store's locale once it has the row.
i18n_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(t('errors.invalid_request_method'));
}
// Retrieve GET parameters
$shop_id = $_GET['shop_id'];
$order_id = $_GET['order_id'];

echo generatepdf($shop_id,$order_id);
exit;
?>