<?php
include 'session.php';
include 'config.php';

$farmerId = $_SESSION['farmerId'];

redirectIfNotLoggedIn();

if ($_SESSION['roleId'] != 2) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['id'];
$query = mysqli_prepare($conn, "SELECT firstName, lastName, email FROM farmers WHERE userId = ?");
mysqli_stmt_bind_param($query, "i", $userId);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);
$farm_info = mysqli_num_rows($result) > 0 ? mysqli_fetch_assoc($result) : ['firstName' => 'Farmer', 'lastName' => '', 'email' => ''];

$farmerQuery = mysqli_prepare($conn, "SELECT id FROM farmers WHERE userId = ?");
mysqli_stmt_bind_param($farmerQuery, "i", $userId);
mysqli_stmt_execute($farmerQuery);
$farmerResult = mysqli_stmt_get_result($farmerQuery);
$farmerData = mysqli_fetch_assoc($farmerResult);

if (!$farmerData) {
    die("Farmer not found!");
}

$farmerId = $farmerData['id'];

// Total Revenue
$revenueQuery = mysqli_prepare($conn, "
    SELECT SUM(lineTotal) AS revenue
    FROM order_items
    WHERE farmerId = ?
");
mysqli_stmt_bind_param($revenueQuery, "i", $farmerId);
mysqli_stmt_execute($revenueQuery);
$revenueResult = mysqli_stmt_get_result($revenueQuery);
$revenueData = mysqli_fetch_assoc($revenueResult);
$totalRevenue = $revenueData['revenue'] ?? 0;

// Pending Orders
$pendingQuery = mysqli_prepare($conn, "
    SELECT COUNT(DISTINCT orderId) AS pendingOrders
    FROM order_items
    WHERE farmerId = ? AND status = 'Pending'
");
mysqli_stmt_bind_param($pendingQuery, "i", $farmerId);
mysqli_stmt_execute($pendingQuery);
$pendingResult = mysqli_stmt_get_result($pendingQuery);
$pendingData = mysqli_fetch_assoc($pendingResult);
$pendingOrders = $pendingData['pendingOrders'] ?? 0;

// Active Products
$productsQuery = mysqli_prepare($conn, "
    SELECT COUNT(*) AS activeProducts
    FROM products
    WHERE farmerId = ? AND stockQuantity > 0
");
mysqli_stmt_bind_param($productsQuery, "i", $farmerId);
mysqli_stmt_execute($productsQuery);
$productsResult = mysqli_stmt_get_result($productsQuery);
$productsData = mysqli_fetch_assoc($productsResult);
$activeProducts = $productsData['activeProducts'] ?? 0;

// Revenue trend (last 30 days)
$revenueTrendQuery = mysqli_prepare($conn, "
    SELECT DATE(o.orderDate) AS orderDay, SUM(oi.lineTotal) AS dailyRevenue
    FROM orders o
    JOIN order_items oi ON o.id = oi.orderId
    WHERE oi.farmerId = ? AND o.orderDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(o.orderDate)
    ORDER BY DATE(o.orderDate)
");
mysqli_stmt_bind_param($revenueTrendQuery, "i", $farmerId);
mysqli_stmt_execute($revenueTrendQuery);
$revenueTrendResult = mysqli_stmt_get_result($revenueTrendQuery);
$revenueTrendData = mysqli_fetch_all($revenueTrendResult, MYSQLI_ASSOC);

$chartLabels = [];
$chartData = [];
foreach ($revenueTrendData as $row) {
    $chartLabels[] = $row['orderDay'];
    $chartData[] = $row['dailyRevenue'];
}

// Order status breakdown
$statusQuery = mysqli_prepare($conn, "
    SELECT status, COUNT(*) AS countStatus
    FROM order_items
    WHERE farmerId = ?
    GROUP BY status
");
mysqli_stmt_bind_param($statusQuery, "i", $farmerId);
mysqli_stmt_execute($statusQuery);
$statusResult = mysqli_stmt_get_result($statusQuery);
$orderStatusData = mysqli_fetch_all($statusResult, MYSQLI_ASSOC);

$statusLabels = [];
$statusCounts = [];
foreach ($orderStatusData as $row) {
    $statusLabels[] = $row['status'];
    $statusCounts[] = $row['countStatus'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Farmer Dashboard | StockCrop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/icon.png">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
<style>
:root {
    --sc-primary-green: #028037;
    --sc-dark-green: #01632c;
    --sc-hover-green: #146c43;
    --sc-background: #f5f7fa;
    --sc-light-text: #e0e0e0;
}
body { font-family: 'Roboto', sans-serif; background-color: var(--sc-background); margin:0; overflow-x: hidden; }
.sidebar { width: 250px; background-color: var(--sc-primary-green); color: #fff; flex-shrink:0; display:flex; flex-direction: column; padding: 1rem 0; position: fixed; top:0; bottom:0; z-index:1030; box-shadow:2px 0 5px rgba(0,0,0,0.1);}
.sidebar .logo-container {padding:10px 20px 30px; text-align:center;}
.sidebar .logo-container img {max-height:40px;}
.sidebar-link {color: var(--sc-light-text); text-decoration:none; padding:12px 25px; display:flex; align-items:center; gap:15px; margin:0 10px; border-radius:8px; transition: background-color 0.3s, color 0.3s;}
.sidebar-link i { font-size:1.2rem; }
.sidebar-link:hover, .sidebar-link.active { background-color: var(--sc-dark-green); color:#fff;}
.sidebar-link.active { font-weight:700; border-left:5px solid #ffc107; padding-left:20px; }
.navbar-top { background-color:#fff; border-bottom:1px solid #ddd; color:#333; z-index:1020; position:fixed; left:250px; right:0; height:60px;}
.navbar-top .navbar-brand { color: var(--sc-primary-green); font-weight:700; font-size:1.2rem; }
.navbar-top .btn-logout { background:#ffc107; color:#212529; font-weight:600; border:none; border-radius:6px; padding:6px 15px; }
.content { margin-left:250px; padding:20px 30px; padding-top:80px; min-height:100vh;}
.dashboard-hero { background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1542838132-92c733004cd8?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat; border-radius:15px; color:#fff; padding:40px; margin-bottom:30px; box-shadow:0 6px 15px rgba(0,0,0,0.1);}
.dashboard-hero h2 { font-weight:700; }
.card-dash { border-radius:12px; border:none; box-shadow:0 4px 12px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;}
.card-dash:hover { transform:translateY(-3px); box-shadow:0 8px 16px rgba(0,0,0,0.12);}
.card-icon { font-size:1.5rem; color: var(--sc-primary-green); margin-right:15px; background-color: rgba(2,128,55,0.1); padding:10px; border-radius:8px;}
@media(max-width:992px){ .sidebar{ position:fixed; left:-250px; transition:left 0.3s;} .content{ margin-left:0; padding-top:70px;} .navbar-top{ left:0; height:70px; padding:0.5rem 1rem;} .navbar-toggler-icon { background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba%282,128,55,1%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");} .navbar-toggler:focus{ box-shadow:none;} .show-sidebar{left:0;}}
</style>
</head>
<body>
<?php include 'sidePanel.php'; ?>

<div class="content">
    <div class="dashboard-hero">
        <h2 class="mb-2">Welcome Back, <?php echo htmlspecialchars($farm_info['firstName']); ?>!</h2>
        <p class="mb-0">Manage your farm's listings, track recent orders, and grow your business with StockCrop.</p>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <!-- Total Revenue -->
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="card card-dash p-3">
                <div class="d-flex align-items-center">
                    <div class="card-icon text-success">
                        <span class="material-icons-outlined">paid</span>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small">Total Revenue</p>
                        <h4 class="fw-bold mb-0">$<?php echo number_format($totalRevenue, 2); ?> JMD</h4>
                    </div>
                </div>
            </div>
        </div>
        <!-- Pending Orders -->
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="card card-dash p-3">
                <div class="d-flex align-items-center">
                    <div class="card-icon text-primary">
                        <span class="material-icons-outlined">local_shipping</span>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small">Pending Orders</p>
                        <h4 class="fw-bold mb-0"><?php echo $pendingOrders; ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <!-- Active Products -->
        <div class="col-lg-4 col-md-12 col-sm-12">
            <div class="card card-dash p-3">
                <div class="d-flex align-items-center">
                    <div class="card-icon text-warning">
                        <span class="material-icons-outlined">inventory_2</span>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small">Active Products</p>
                        <h4 class="fw-bold mb-0"><?php echo $activeProducts; ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h4 class="mb-4 fw-bold text-dark">Quick Actions</h4>
    <div class="row g-4 mb-5">
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="card card-dash p-4 text-center">
                <span class="material-icons-outlined mx-auto mb-3" style="font-size:3rem; color: var(--sc-primary-green);">library_add</span>
                <h5 class="card-title fw-bold">Add New Product</h5>
                <p class="card-text text-muted small">Quickly list fresh produce and set prices for market.</p>
                <a href="addProduct.php" class="btn btn-success mt-2">Add Listing</a>
            </div>
        </div>

        <!-- QR Code Section -->
<div class="row mb-5">
    <div class="col-md-6">
        <div class="card p-4 text-center">
            <h5>Your Unique Farm QR Code</h5>
            <p class="text-muted small">
                Customers can scan this to view your farm profile.
            </p>

            <?php
            $qrUrl = "http://localhost/stockcrop/viewFarmer.php?id=" . $farmerId;
            ?>

            <img 
                src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($qrUrl); ?>" 
                alt="QR Code"
                class="mb-3"
            >

            <br>

            <a 
                href="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?php echo urlencode($qrUrl); ?>"
                download="Farm_QR_<?php echo $farmerId; ?>.png"
                class="btn btn-success"
            >
                Download QR Code
            </a>
        </div>
    </div>
</div>

        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="card card-dash p-4 text-center">
                <span class="material-icons-outlined mx-auto mb-3" style="font-size:3rem; color:#007bff;">storage</span>
                <h5 class="card-title fw-bold">Manage Inventory</h5>
                <p class="card-text text-muted small">Update stock, edit details, or remove products easily.</p>
                <a href="viewProducts.php" class="btn btn-outline-success mt-2">View Products</a>
            </div>
        </div>
        <div class="col-lg-4 col-md-12 col-sm-12">
            <div class="card card-dash p-4 text-center">
                <span class="material-icons-outlined mx-auto mb-3" style="font-size:3rem; color:#ffc107;">track_changes</span>
                <h5 class="card-title fw-bold">Process Orders</h5>
                <p class="card-text text-muted small">Track and manage all customer orders and deliveries.</p>
                <a href="viewOrders.php" class="btn btn-outline-success mt-2">View Orders</a>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-5">
        <!-- Revenue Trend Chart -->
        <div class="col-lg-6 col-md-12">
            <div class="card card-dash p-4">
                <h5 class="mb-3">Revenue (Last 30 Days)</h5>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <!-- Order Status Chart -->
        <div class="col-lg-6 col-md-12">
            <div class="card card-dash p-4">
                <h5 class="mb-3">Orders Status</h5>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Line Chart
new Chart(document.getElementById('revenueChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Revenue (JMD)',
            data: <?= json_encode($chartData); ?>,
            backgroundColor: 'rgba(2,128,55,0.2)',
            borderColor: 'rgba(2,128,55,1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive:true, plugins:{legend:{display:false}} }
});

// Orders Status Doughnut Chart
new Chart(document.getElementById('statusChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statusLabels); ?>,
        datasets: [{
            data: <?= json_encode($statusCounts); ?>,
            backgroundColor: ['#ffc107','#28a745','#007bff','#dc3545','#6c757d']
        }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
});
</script>
</body>
</html>
