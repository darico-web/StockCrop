<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['id'];

// Get the customerId for the logged-in user
$stmt = mysqli_prepare($conn, "SELECT id AS customerId, firstName, lastName, phoneNumber, address1, address2, parish FROM customers WHERE userId=?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);
$customerId = $customer['customerId'] ?? 0;
$firstName = htmlspecialchars($customer['firstName'] ?? 'Customer');
$lastName = htmlspecialchars($customer['lastName'] ?? '');
$fullName = $firstName . ' ' . $lastName;
$phone = htmlspecialchars($customer['phoneNumber'] ?? 'N/A');
$address = implode(', ', array_filter([
    htmlspecialchars($customer['address1'] ?? ''),
    htmlspecialchars($customer['address2'] ?? ''),
    htmlspecialchars($customer['parish'] ?? '')
]));
mysqli_stmt_close($stmt);

// Fetch user email from users table
$stmt = mysqli_prepare($conn, "SELECT email FROM users WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
$email = htmlspecialchars($user['email'] ?? 'N/A');
mysqli_stmt_close($stmt);

// Fetch latest 5 notifications for this customer
$stmt = mysqli_prepare($conn, "SELECT * FROM notifications WHERE userId=? ORDER BY created_at DESC LIMIT 5");
mysqli_stmt_bind_param($stmt, "i", $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Count unread notifications
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS unreadCount FROM notifications WHERE userId=? AND isRead=0");
mysqli_stmt_bind_param($stmt, "i", $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$unreadCount = $row['unreadCount'] ?? 0;
mysqli_stmt_close($stmt);

// Fetch customer orders
$orderQuery = mysqli_prepare($conn, "SELECT id, orderDate, totalAmount, status FROM orders WHERE customerId=? ORDER BY orderDate DESC");
mysqli_stmt_bind_param($orderQuery, "i", $customerId);
mysqli_stmt_execute($orderQuery);
$ordersResult = mysqli_stmt_get_result($orderQuery);
$orders = mysqli_fetch_all($ordersResult, MYSQLI_ASSOC);
mysqli_stmt_close($orderQuery);

$totalOrders = count($orders);
$inProgressOrders = count(array_filter($orders, fn($o) => $o['status'] !== 'Delivered'));
$latestOrder = $orders[0]['orderDate'] ?? null;


// Fetch user info from users + customers
$query = mysqli_prepare($conn, "
    SELECT u.email, c.id AS customerId, c.firstName, c.lastName, c.phoneNumber, c.address1, c.address2, c.parish
    FROM users u
    LEFT JOIN customers c ON u.id = c.userId
    WHERE u.id = ?
");
mysqli_stmt_bind_param($query, "i", $userId);
mysqli_stmt_execute($query);
$result = mysqli_stmt_get_result($query);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($query);

$firstName = htmlspecialchars($user['firstName'] ?? 'Customer');
$lastName = htmlspecialchars($user['lastName'] ?? '');
$fullName = $firstName . ' ' . $lastName;
$email = htmlspecialchars($user['email'] ?? 'N/A');
$phone = htmlspecialchars($user['phoneNumber'] ?? 'N/A');
$address = implode(', ', array_filter([
    htmlspecialchars($user['address1'] ?? ''),
    htmlspecialchars($user['address2'] ?? ''),
    htmlspecialchars($user['parish'] ?? '')
]));

$customerId = $user['customerId'] ?? 0;

// Fetch customer orders
$orderQuery = mysqli_prepare($conn, "
    SELECT id, orderDate, totalAmount, status
    FROM orders
    WHERE customerId = ?
    ORDER BY orderDate DESC
");
mysqli_stmt_bind_param($orderQuery, "i", $customerId);
mysqli_stmt_execute($orderQuery);
$ordersResult = mysqli_stmt_get_result($orderQuery);
$orders = mysqli_fetch_all($ordersResult, MYSQLI_ASSOC);
mysqli_stmt_close($orderQuery);

$totalOrders = count($orders);
$inProgressOrders = count(array_filter($orders, fn($o) => $o['status'] !== 'Delivered'));
$latestOrder = $orders[0]['orderDate'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StockCrop | Customer Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<link rel="stylesheet" href="styles.css">
<link rel="icon" type="image/png" href="assets/icon.png">
<style>
:root {
    --bs-stockcrop-green: #198754;
    --bs-background-light: #f4f6f9;
    --bs-border-subtle: #e0e4eb;
    --stockcrop-green: #388E3C;
    --stockcrop-orange: #FF8F00;
}
body { background-color: var(--bs-background-light); }
.dashboard-header {
    background-image: linear-gradient(to right, var(--stockcrop-green), var(--stockcrop-orange));
    padding: 1.5rem 0;
    border: none;
}
.dashboard-header .container h1 { color: #fff !important; }
.dashboard-header .container p.lead { color: #e0e0e0 !important; }
.hero-img { margin-left: 1rem; flex-shrink: 0; }
.opacity-control { opacity: 0.2; }
.nav-link.dash-tab { color: var(--bs-gray-600); border: none; border-bottom: 2px solid transparent; font-weight: 500; transition: all 0.2s; }
.nav-link.dash-tab.active, .nav-link.dash-tab:hover { color: var(--bs-stockcrop-green); border-bottom: 2px solid var(--bs-stockcrop-green); background-color: transparent; }
.action-card { border-radius: 0.5rem; border: 1px solid var(--bs-border-subtle); height: 100%; background-color: var(--bs-white); transition: transform 0.2s, box-shadow 0.2s; }
.action-card:hover { transform: translateY(-3px); box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,0.05); }
.detail-card { border-left: 4px solid var(--bs-stockcrop-green); }
.stat-icon { color: var(--bs-gray-500); }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="dashboard-header">
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 class="display-6 fw-bold mb-1">Hello, <?= $firstName ?></h1>
            <p class="lead mb-0">Manage your profile and track your orders.</p>
        </div>
        <div class="d-none d-sm-block">
            <img src="assets/produceOutline.png" alt="Dashboard Hero" class="img-fluid hero-img opacity-control" style="max-height: 150px;">
        </div>
    </div>
</div>

<div class="dropdown ms-auto">
    <button class="btn btn-secondary position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell"></i>Notifications <!-- Bootstrap bell icon -->
        <?php if($unreadCount > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $unreadCount ?>
        </span>
        <?php endif; ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="notificationDropdown" style="min-width: 300px;">
        <?php if(empty($notifications)): ?>
            <li class="text-center text-muted">No notifications</li>
        <?php else: ?>
            <?php foreach($notifications as $note): ?>
            <li class="dropdown-item <?= $note['isRead'] == 0 ? 'fw-bold' : '' ?>">
                <?= htmlspecialchars($note['message']) ?><br>
                <small class="text-muted"><?= date('M d, Y H:i', strtotime($note['created_at'])) ?></small>
            </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>



<div class="container py-5">
    <ul class="nav nav-tabs mb-4 border-0" id="dashboardTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link dash-tab active" data-bs-toggle="tab" data-bs-target="#overview">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:20px;">home</span> Overview
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link dash-tab" id="orders-tab-btn" data-bs-toggle="tab" data-bs-target="#orders">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:20px;">package</span> Orders
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link dash-tab" data-bs-toggle="tab" data-bs-target="#profile">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:20px;">person</span> Profile
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link dash-tab" data-bs-toggle="tab" data-bs-target="#wishlist">
                <span class="material-symbols-outlined align-middle me-1" style="font-size:20px;">favorite</span> Wishlist
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Overview -->
        <div class="tab-pane fade show active" id="overview">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card action-card p-4 text-center shadow-sm">
                        <span class="material-symbols-outlined display-3 stat-icon mb-2">local_shipping</span>
                        <h5 class="fw-bold mb-1"><?= $inProgressOrders ?> Orders in Progress</h5>
                        <p class="text-muted small">Check the status of your deliveries.</p>
                        <button class="btn btn-sm btn-outline-secondary mt-2 w-75 mx-auto" id="viewOrdersBtn">View Orders</button>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card action-card p-4 text-center shadow-sm">
                        <span class="material-symbols-outlined display-3 stat-icon mb-2">account_box</span>
                        <h5 class="fw-bold mb-1">Update Account Info</h5>
                        <p class="text-muted small">Manage your name, phone, and address.</p>
                        <a href="editProfile.php" class="btn btn-sm btn-success mt-2 w-75 mx-auto">Edit Profile</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="card action-card p-4 shadow-sm">
                        <h5 class="fw-bold mb-3 d-flex align-items-center text-dark">
                            <span class="material-symbols-outlined me-2 stat-icon">update</span> Latest Activity
                        </h5>
                        <p class="mb-1"><strong>Last Order:</strong> <?= $latestOrder ? date('F j, Y', strtotime($latestOrder)) : 'N/A' ?></p>
                        <p class="mb-1 text-muted small">You have <?= $totalOrders ?> total orders.</p>
                        <div class="mt-3 pt-3 border-top">
                            <a href="shop.php" class="btn btn-sm btn-outline-secondary w-100">Continue Shopping</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders -->
        <div class="tab-pane fade" id="orders">
            <h3 class="mb-3 fw-bold">My Orders</h3>
            <?php if(empty($orders)): ?>
                <div class="alert alert-info text-center bg-white border-0 shadow-sm">
                    <span class="material-symbols-outlined display-6 d-block mb-2 text-primary">list_alt</span>
                    <p class="mb-0">You have no active or past orders on record.</p>
                    <a href="shop.php" class="alert-link fw-semibold">Start your first order now.</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle bg-white shadow-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                                <tr>
                                    <td><?= $order['id'] ?></td>
                                    <td><?= date('M j, Y', strtotime($order['orderDate'])) ?></td>
                                    <td><span class="badge bg-<?= $order['status']==='Delivered'?'success':'warning' ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                    <td>$<?= number_format($order['totalAmount'],2) ?></td>
                                    <td><a href="viewOrderDetails.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-success">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile -->
        <div class="tab-pane fade" id="profile">
            <h3 class="mb-3 fw-bold">Account Details</h3>
            <div class="card p-4 shadow-sm detail-card bg-white">
                <h5 class="fw-bold mb-3 text-dark">Personal Information & Address</h5>
                <div class="row g-3 text-muted">
                    <div class="col-md-6"><span class="fw-semibold text-dark">Name:</span> <?= $fullName ?></div>
                    <div class="col-md-6"><span class="fw-semibold text-dark">Email:</span> <?= $email ?></div>
                    <div class="col-md-6"><span class="fw-semibold text-dark">Phone:</span> <?= $phone ?></div>
                    <div class="col-md-6"><span class="fw-semibold text-dark">Address:</span> <?= $address?:'Not set' ?></div>
                </div>
                <div class="mt-4 pt-3 border-top">
                    <a href="editProfile.php" class="btn btn-success me-2">
                        <span class="material-symbols-outlined align-middle" style="font-size:18px;">edit</span> Edit Profile
                    </a>
                    <a href="changePassword.php" class="btn btn-outline-secondary">Change Password</a>
                </div>
            </div>
        </div>

        <!-- Wishlist -->
        <div class="tab-pane fade" id="wishlist">
            <h3 class="mb-3 fw-bold">My Wishlist</h3>
            <div class="alert alert-warning text-center bg-white border-0 shadow-sm">
                <span class="material-symbols-outlined display-6 d-block mb-2 text-warning">favorite</span>
                <p class="mb-0">Your wishlist is currently empty.</p>
                <a href="shop.php" class="alert-link fw-semibold">Browse Products</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Activate tab based on hash
function activateTabFromHash() {
    const hash = window.location.hash || '#overview';
    const tabButton = document.querySelector(`.nav-link.dash-tab[data-bs-target="${hash}"]`);
    if(tabButton) new bootstrap.Tab(tabButton).show();
}
document.addEventListener('DOMContentLoaded', activateTabFromHash);
window.addEventListener('hashchange', activateTabFromHash);

// View Orders button functionality
document.addEventListener('DOMContentLoaded', function() {
    const viewOrdersBtn = document.getElementById('viewOrdersBtn');
    const ordersTabBtn = document.getElementById('orders-tab-btn');

    viewOrdersBtn.addEventListener('click', function() {
        const ordersTab = new bootstrap.Tab(ordersTabBtn);
        ordersTab.show();
        ordersTabBtn.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
</script>

<script>
document.getElementById('notificationDropdown').addEventListener('click', () => {
    fetch('markRead.php')
        .then(res => res.text())
        .then(console.log);
});
</script>
<script>
setInterval(() => {
    fetch('fetchUnreadCount.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.querySelector('.badge');
            if(badge){
                badge.textContent = data.unread;
                badge.style.display = data.unread > 0 ? 'inline-block' : 'none';
            }
        });
}, 5000);
</script>

</body>
</html>
