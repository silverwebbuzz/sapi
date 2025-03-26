<?php require_once '../config.php'; 
require_once '../db.php';
require_once '../shopify/shopify_functions.php';

// Check if store_id is provided
if (!isset($_GET['shop_id'])) {
  die("Invalid request. Shop ID missing.");
}

$shop_id = intval($_GET['shop_id']);
$conn = DB::getInstance();

// Fetch Store Information
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$store_result = $stmt->get_result();

if ($store_result->num_rows === 0) {
  die("Store not found.");
}

$store = $store_result->fetch_assoc();

// Fetch Active Subscription
$stmt = $conn->prepare("
  SELECT s.*, p.name AS plan_name, p.order_limit, p.price
  FROM store_subscriptions s
  JOIN plans p ON s.plan_id = p.id
  WHERE s.store_id = ? AND s.status = 'active'
");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$subscription_result = $stmt->get_result();

$subscription = $subscription_result->num_rows > 0 ? $subscription_result->fetch_assoc() : null;


//fetch invoices
$invoice_table = "invoices_" . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($store['shop']));

$invoices_query = $conn->query("SELECT * FROM `$invoice_table` ORDER BY created_at DESC LIMIT 10");

?>
<!doctype html>

<html
  lang="en"
  class="layout-navbar-fixed layout-menu-fixed layout-compact"
  dir="ltr"
  data-skin="default"
  data-assets-path="<?= BASE_TEMPLATE_URL ?>assets/"
  data-template="horizontal-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Dashboard - SAPi</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= BASE_TEMPLATE_URL ?>assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/fonts/iconify-icons.css" />

    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->

    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/node-waves/node-waves.css" />

    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/pickr/pickr-themes.css" />

    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/css/core.css" />
    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/css/demo.css" />

    <!-- Vendors CSS -->

    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- endbuild -->

    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/apex-charts/apex-charts.css" />
    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/swiper/swiper.css" />
    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/fonts/flag-icons.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="<?= BASE_TEMPLATE_URL ?>assets/vendor/css/pages/cards-advance.css" />

    <!-- Helpers -->
    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/js/helpers.js"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->

    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/js/template-customizer.js"></script>

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->

    <script src="<?= BASE_TEMPLATE_URL ?>assets/js/config.js"></script>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">
      <div class="layout-container">
        <!-- Navbar -->
        <?php require_once 'navbar.php'; ?>
        <!--/ Navbar -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Content wrapper -->
          <div class="content-wrapper">

            <!-- Menu -->
            <?php require_once 'menu.php'; ?>
            <!-- Menu -->

            <!-- Content -->
            <div class="container-fluid flex-grow-1 container-p-y">
              <div class="row g-6">
                <!-- Website Analytics -->
                <div class="col-xl-6 col">
                  <div
                    class="swiper-container swiper-container-horizontal swiper swiper-card-advance-bg"
                    id="swiper-with-pagination-cards">
                    <div class="swiper-wrapper">
                      <div class="swiper-slide">
                        <div class="row">
                          <div class="col-12">
                            <h5 class="text-white mb-0">Website Analytics</h5>
                            <small>Total 28.5% Conversion Rate</small>
                          </div>
                          <div class="row">
                            <div class="col-lg-7 col-md-9 col-12 order-2 order-md-1 pt-md-9">
                              <h6 class="text-white mt-0 mt-md-3 mb-4">Traffic</h6>
                              <div class="row">
                                <div class="col-6">
                                  <ul class="list-unstyled mb-0">
                                    <li class="d-flex mb-4 align-items-center">
                                      <p class="mb-0 fw-medium me-2 website-analytics-text-bg">28%</p>
                                      <p class="mb-0">Sessions</p>
                                    </li>
                                    <li class="d-flex align-items-center">
                                      <p class="mb-0 fw-medium me-2 website-analytics-text-bg">1.2k</p>
                                      <p class="mb-0">Leads</p>
                                    </li>
                                  </ul>
                                </div>
                                <div class="col-6">
                                  <ul class="list-unstyled mb-0">
                                    <li class="d-flex mb-4 align-items-center">
                                      <p class="mb-0 fw-medium me-2 website-analytics-text-bg">3.1k</p>
                                      <p class="mb-0">Page Views</p>
                                    </li>
                                    <li class="d-flex align-items-center">
                                      <p class="mb-0 fw-medium me-2 website-analytics-text-bg">12%</p>
                                      <p class="mb-0">Conversions</p>
                                    </li>
                                  </ul>
                                </div>
                              </div>
                            </div>
                            <div class="col-lg-5 col-md-3 col-12 order-1 order-md-2 my-4 my-md-0 text-center">
                              <img
                                src="<?= BASE_TEMPLATE_URL ?>assets/img/illustrations/card-website-analytics-1.png"
                                alt="Website Analytics"
                                height="150"
                                class="card-website-analytics-img" />
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="swiper-slide">
                        <div class="row">
                          <div class="col-12">
                            <h5 class="text-white mb-0">Website Analytics</h5>
                            <small>Total 28.5% Conversion Rate</small>
                          </div>
                          <div class="col-lg-7 col-md-9 col-12 order-2 order-md-1 pt-md-9">
                            <h6 class="text-white mt-0 mt-md-3 mb-4">Spending</h6>
                            <div class="row">
                              <div class="col-6">
                                <ul class="list-unstyled mb-0">
                                  <li class="d-flex mb-4 align-items-center">
                                    <p class="mb-0 fw-medium me-2 website-analytics-text-bg">12h</p>
                                    <p class="mb-0">Spend</p>
                                  </li>
                                  <li class="d-flex align-items-center">
                                    <p class="mb-0 fw-medium me-2 website-analytics-text-bg">127</p>
                                    <p class="mb-0">Order</p>
                                  </li>
                                </ul>
                              </div>
                              <div class="col-6">
                                <ul class="list-unstyled mb-0">
                                  <li class="d-flex mb-4 align-items-center">
                                    <p class="mb-0 fw-medium me-2 website-analytics-text-bg">18</p>
                                    <p class="mb-0">Order Size</p>
                                  </li>
                                  <li class="d-flex align-items-center">
                                    <p class="mb-0 fw-medium me-2 website-analytics-text-bg">2.3k</p>
                                    <p class="mb-0">Items</p>
                                  </li>
                                </ul>
                              </div>
                            </div>
                          </div>
                          <div class="col-lg-5 col-md-3 col-12 order-1 order-md-2 my-4 my-md-0 text-center">
                            <img
                              src="<?= BASE_TEMPLATE_URL ?>assets/img/illustrations/card-website-analytics-2.png"
                              alt="Website Analytics"
                              height="150"
                              class="card-website-analytics-img" />
                          </div>
                        </div>
                      </div>
                      
                    </div>
                    <div class="swiper-pagination"></div>
                  </div>
                </div>
                <!--/ Website Analytics -->

                <!-- Average Daily Sales -->
                <div class="col-xl-3 col-sm-6">
                  <div class="card h-100">
                    <div class="card-header pb-0">
                      <h5 class="mb-3 card-title">Average Daily Sales</h5>
                      <p class="mb-0 text-body">Total Sales This Month</p>
                      <h4 class="mb-0">$28,450</h4>
                    </div>
                    <div class="card-body px-0">
                      <div id="averageDailySales"></div>
                    </div>
                  </div>
                </div>
                <!--/ Average Daily Sales -->

                <!-- Sales Overview -->
                <div class="col-xl-3 col-sm-6">
                  <div class="card h-100">
                    <div class="card-header">
                      <div class="d-flex justify-content-between">
                        <p class="mb-0 text-body">Sales Overview</p>
                        <p class="card-text fw-medium text-success">+18.2%</p>
                      </div>
                      <h4 class="card-title mb-1">$42.5k</h4>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col-4">
                          <div class="d-flex gap-2 align-items-center mb-2">
                            <span class="badge bg-label-info p-1 rounded"
                              ><i class="icon-base ti tabler-shopping-cart icon-sm"></i
                            ></span>
                            <p class="mb-0">Order</p>
                          </div>
                          <h5 class="mb-0 pt-1">62.2%</h5>
                          <small class="text-body-secondary">6,440</small>
                        </div>
                        <div class="col-4">
                          <div class="divider divider-vertical">
                            <div class="divider-text">
                              <span class="badge-divider-bg bg-label-secondary">VS</span>
                            </div>
                          </div>
                        </div>
                        <div class="col-4 text-end">
                          <div class="d-flex gap-2 justify-content-end align-items-center mb-2">
                            <p class="mb-0">Visits</p>
                            <span class="badge bg-label-primary p-1 rounded"
                              ><i class="icon-base ti tabler-link icon-sm"></i
                            ></span>
                          </div>
                          <h5 class="mb-0 pt-1">25.5%</h5>
                          <small class="text-body-secondary">12,749</small>
                        </div>
                      </div>
                      <div class="d-flex align-items-center mt-6">
                        <div class="progress w-100" style="height: 10px">
                          <div
                            class="progress-bar bg-info"
                            style="width: 70%"
                            role="progressbar"
                            aria-valuenow="70"
                            aria-valuemin="0"
                            aria-valuemax="100"></div>
                          <div
                            class="progress-bar bg-primary"
                            role="progressbar"
                            style="width: 30%"
                            aria-valuenow="30"
                            aria-valuemin="0"
                            aria-valuemax="100"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!--/ Sales Overview -->

                <!-- Order Invoice table -->
                <div class="col-xxl-12">
                  <div class="card">
                    <div class="table-responsive mb-4">
                      <table id="myTable" class="table">
                        <thead class="table-dark">
                          <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Invoice</th>
                          </tr>
                        </thead>
                        <tbody>
                            <?php while ($invoice = $invoices_query->fetch_assoc()): ?>
                              <tr>
                                  <td><?= $invoice['order_id'] ?></td>
                                  <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                  <td>$<?= number_format($invoice['total_price'], 2) ?></td>
                                  <td><?= ucfirst($invoice['invoice_status']) ?></td>
                                  <td><?= $invoice['created_at'] ?></td>
                                  <td><?= BASE_URL ?>/generatepdf?shop_id=<?= $shop_id ?>&order_id<?= $invoice['order_id'] ?></td>
                              </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <!--/ Order Invoice table -->
              </div>
            </div>
            <!--/ Content -->
            <!-- Footer -->
            <?php require_once 'footer.php'; ?>
            <!--/ Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!--/ Content wrapper -->
        </div>

        <!--/ Layout container -->
      </div>
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>

    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>

    <!--/ Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/theme.js -->

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/jquery/jquery.js"></script>

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/popper/popper.js"></script>
    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/js/bootstrap.js"></script>
    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/node-waves/node-waves.js"></script>

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/@algolia/autocomplete-js.js"></script>

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/pickr/pickr.js"></script>

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/hammer/hammer.js"></script>

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/i18n/i18n.js"></script>

    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/js/menu.js"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/swiper/swiper.js"></script>
    <script src="<?= BASE_TEMPLATE_URL ?>assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>

    <!-- Main JS -->

    <script src="<?= BASE_TEMPLATE_URL ?>assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="<?= BASE_TEMPLATE_URL ?>assets/js/dashboards-analytics.js"></script>
    <script>
        $(document).ready(function() {
            $('#myTable').DataTable();
        });
    </script>
  </body>
</html>
