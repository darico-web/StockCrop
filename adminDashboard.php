<?php
session_start();
include 'config.php';
include 'session.php';
redirectIfNotLoggedIn();

if ($_SESSION['roleId'] != 1) {
    header("Location: login.php");
    exit();
}

// Total sales for current month
$stmt = mysqli_prepare($conn, "
    SELECT IFNULL(SUM(totalAmount), 0) AS total_sales
    FROM orders
    WHERE MONTH(orderDate) = MONTH(CURDATE())
    AND YEAR(orderDate) = YEAR(CURDATE())
");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$totalSalesRow = mysqli_fetch_assoc($result);
$totalSales = $totalSalesRow['total_sales'] ?? 0;
mysqli_stmt_close($stmt);

// Pending orders (all time, not just today)
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS pending_orders
    FROM orders
    WHERE status = 'Pending'
");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pendingOrdersRow = mysqli_fetch_assoc($result);
$pendingOrders = $pendingOrdersRow['pending_orders'] ?? 0;
mysqli_stmt_close($stmt);


// Count total farmers
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total_farmers
    FROM farmers
");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$farmersRow = mysqli_fetch_assoc($result);
$totalFarmers = $farmersRow['total_farmers'] ?? 0;
mysqli_stmt_close($stmt);

// Weekly Sales Trend (Last 7 days)
$salesData = [];
$stmt = mysqli_prepare($conn, "
    SELECT DATE(orderDate) AS order_day, IFNULL(SUM(totalAmount),0) AS total_sales
    FROM orders
    WHERE orderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(orderDate)
    ORDER BY DATE(orderDate)
");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while($row = mysqli_fetch_assoc($result)){
    $salesData[$row['order_day']] = $row['total_sales'];
}
mysqli_stmt_close($stmt);

// Fill missing days with 0
for($i=6; $i>=0; $i--){
    $day = date('Y-m-d', strtotime("-$i days"));
    if(!isset($salesData[$day])){
        $salesData[$day] = 0;
    }
}
ksort($salesData);

// Format chart labels
$formattedLabels = array_map(function($date) {
    return date('M d', strtotime($date));
}, array_keys($salesData));

$chartLabels = json_encode($formattedLabels);
$chartValues = json_encode(array_values($salesData));

// Recent Activity Feed
$activities = [];

// Latest 3 farmers
$stmt = mysqli_prepare($conn, "
    SELECT firstName, lastName, created_at
    FROM farmers
    ORDER BY created_at DESC
    LIMIT 3
");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while($row = mysqli_fetch_assoc($result)){
    $activities[] = [
        'text' => "New Farmer Registered: " . htmlspecialchars("{$row['firstName']} {$row['lastName']}"),
        'icon' => 'person_add'
    ];
}
mysqli_stmt_close($stmt);

// Latest 2 orders
$stmt = mysqli_prepare($conn, "
    SELECT id, orderDate
    FROM orders
    ORDER BY orderDate DESC
    LIMIT 2
");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while($row = mysqli_fetch_assoc($result)){
    $activities[] = [
        'text' => "Order #{$row['id']} Placed",
        'icon' => 'shopping_cart'
    ];
}
mysqli_stmt_close($stmt);

// Limit to 5 activities
$activities = array_slice($activities, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard | StockCrop</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<link rel="icon" type="image/png" href="assets/icon.png">

<style>
:root {
    --primary-green: #2f8f3f;
    --dark-green: #1b5e20;
    --light-bg: #f8faf8;
    --sidebar-width: 250px;
}

body {
    display: flex;
    min-height: 100vh;
    background: var(--light-bg);
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.sidebar {
    width: var(--sidebar-width);
    background: var(--primary-green);
    color: white;
    position: fixed;
    height: 100vh;
    padding-top: 20px;
    box-shadow: 4px 0 10px rgba(0,0,0,0.15);
    z-index: 1000;
}
.sidebar .logo {
    font-size: 1.5rem;
    font-weight: bold;
    text-align: center;
    padding: 10px 20px 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}
.sidebar a {
    color: white;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    text-decoration: none;
    font-size: 15px;
    transition: background 0.2s, border-left 0.2s;
}
.sidebar a:hover, .sidebar a.active {
    background: var(--dark-green);
    border-left: 4px solid white;
}
.sidebar a .material-symbols-outlined {
    margin-right: 10px;
    font-size: 1.2rem;
}

.content {
    margin-left: var(--sidebar-width);
    padding: 30px;
    width: calc(100% - var(--sidebar-width));
    flex-grow: 1;
}

.kpi-card {
    padding: 20px;
    border-radius: 10px;
    background: white;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    border-left: 5px solid;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s;
    min-height: 120px;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.kpi-card h3 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0;
}
.kpi-icon-bg {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 3rem;
    opacity: 0.15;
    color: var(--primary-green);
}
.kpi-sales { border-left-color: #28a745; }
.kpi-orders { border-left-color: #ffc107; }
.kpi-farmers { border-left-color: #17a2b8; }

.chart-area, .activity-feed {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}
.activity-feed .list-group-item {
    border: none;
    padding: 10px 0;
    display: flex;
    align-items: center;
}
.activity-feed .list-group-item:not(:last-child) {
    border-bottom: 1px solid #f0f0f0;
}
.activity-feed .material-symbols-outlined {
    margin-right: 10px;
    font-size: 1.2rem;
    color: #6c757d;
}

@media(max-width:992px) {
    .sidebar { width: 100%; height: auto; position: relative; padding-top: 10px; }
    .content { margin-left: 0; width: 100%; padding-top: 20px; }
    .kpi-card { min-height: auto; }
}
</style>
</head>
<body>
<?php include 'adminSidePanel.php'; ?>

<div class="content">
    <h2 class="fw-bold mb-4 mt-5">Dashboard Overview ✨</h2>

    <div class="row g-4 mb-5">
        <div class="col-lg-4 col-md-6">
            <div class="kpi-card kpi-sales">
                <span class="material-symbols-outlined kpi-icon-bg text-success">payments</span>
                <small class="text-secondary">This Month</small>
                <h6>Total Sales</h6>
                <h3 class="text-success">$<?= number_format($totalSales, 2); ?></h3>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="kpi-card kpi-orders">
                <span class="material-symbols-outlined kpi-icon-bg text-warning">inventory_2</span>
                <small class="text-secondary">Needs Action</small>
                <h6>New Orders</h6>
                <h3 class="text-warning"><?= $pendingOrders; ?> Pending</h3>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="kpi-card kpi-farmers">
                <span class="material-symbols-outlined kpi-icon-bg text-info">group</span>
                <small class="text-secondary">Total Registered</small>
                <h6>Farmers</h6>
                <h3 class="text-info"><?= $totalFarmers ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-3">
        <div class="col-lg-8">
            <div class="chart-area">
                <h6>Weekly Sales Trend (Last 7 Days) 📈</h6>
                <div style="height: 300px;"><canvas id="salesChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="activity-feed">
                <h6>Recent Activity 🔔</h6>
                <ul class="list-group small list-group-flush">
                    <?php foreach($activities as $act): ?>
                        <li class="list-group-item">
                            <span class="material-symbols-outlined"><?= $act['icon'] ?></span>
                            <?= htmlspecialchars($act['text']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
            label: 'Sales ($)',
            data: <?= $chartValues ?>,
            borderColor: '#2f8f3f',
            backgroundColor: 'rgba(47, 143, 63, 0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            x: { title: { display: true, text: 'Date' }, grid: { display: false } },
            y: { 
                title: { display: true, text: 'Sales ($)' }, 
                beginAtZero: true,
                ticks: { callback: function(value) { return '$' + value; } }
            }
        }
    }
});
</script>
</body>
</html>
