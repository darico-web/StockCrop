<?php
session_start();
include 'config.php';
include 'session.php';

// Only allow ADMIN (roleId = 1)
redirectIfNotLoggedIn();
if ($_SESSION['roleId'] != 1) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_GET['id'] ?? 0;
$customerData = null;
$orders = [];
$customerRoleId = 3; 

if ($userId === 0) {
    header("Location: customerAccounts.php");
    exit();
}

// --- 1. Fetch Customer Profile Data ---
$profileQuery = "
    SELECT 
        u.id AS userId,
        u.email,
        u.created_at,
        c.firstName,
        c.lastName,
        c.phoneNumber,
        c.address1,
        c.address2,
        c.parish,
        c.id AS customerId  /* Fetch customerId to use in orders query */
    FROM users u
    LEFT JOIN customers c ON u.id = c.userId
    WHERE u.id = ? AND u.roleId = ?
";

$stmt = mysqli_prepare($conn, $profileQuery);
mysqli_stmt_bind_param($stmt, "ii", $userId, $customerRoleId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $customerData = $row;
} else {
    // Customer not found or role mismatch
    header("Location: customerAccounts.php");
    exit();
}
mysqli_stmt_close($stmt);

// Check if we retrieved the customer ID for linking orders
$customerId = $customerData['customerId'] ?? 0;

// --- 2. Fetch Customer Orders (Last 10 for simplicity) ---
// CORRECTED: Using customerId column in the WHERE clause
if ($customerId > 0) {
    $ordersQuery = "
        SELECT 
            id AS orderId,
            orderDate,
            totalAmount,
            status,
            deliveryMethod
        FROM orders
        WHERE customerId = ? 
        ORDER BY orderDate DESC
        LIMIT 10
    ";
    $stmt = mysqli_prepare($conn, $ordersQuery);
    // Bind the customerId found in the profile fetch
    mysqli_stmt_bind_param($stmt, "i", $customerId); 
    mysqli_stmt_execute($stmt);
    $ordersResult = mysqli_stmt_get_result($stmt);

    if ($ordersResult) {
        $orders = mysqli_fetch_all($ordersResult, MYSQLI_ASSOC);
    }
    mysqli_stmt_close($stmt);
}


$fullName = htmlspecialchars($customerData['firstName'] . ' ' . $customerData['lastName']);
$fullName = trim($fullName) ?: htmlspecialchars($customerData['email']);

// Using hardcoded status since the column was omitted earlier
$accountStatus = 'Active'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile: <?= $fullName ?> | StockCrop Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/png" href="assets/icon.png">

    <style>
        :root {
            --primary-green: #2f8f3f;
            --sidebar-width: 250px;
        }

        body {
            background: #f8faf8;
            display: flex;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 20px;
        }

        .profile-detail p {
            margin-bottom: 5px;
        }
        
        .nav-tabs .nav-link.active {
            background-color: var(--primary-green) !important;
            color: white !important;
            border-color: var(--primary-green) !important;
        }
        
        .nav-tabs .nav-link {
            color: var(--primary-green);
        }

        .status-active {
            color: var(--primary-green);
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold mb-0">
                <a href="customerAccounts.php" class="text-secondary text-decoration-none me-2">&leftarrow;</a>
                Customer Profile: <?= $fullName ?>
            </h2>
            <p class="text-muted">User ID: #<?= $userId ?></p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-warning" onclick="alert('Functionality to suspend/edit account to be implemented.')">
                <span class="material-symbols-outlined align-middle me-1">lock_open</span>
                Manage Account
            </button>
        </div>
    </div>

    <div class="profile-card">
        <div class="row">
            <div class="col-md-6 border-end">
                <h4 class="text-secondary mb-3">Contact Information</h4>
                <div class="profile-detail">
                    <p><strong>Email:</strong> <?= htmlspecialchars($customerData['email']); ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($customerData['phoneNumber'] ?? 'N/A'); ?></p>
                    <p><strong>Joined:</strong> <?= date("M j, Y", strtotime($customerData['created_at'])); ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <h4 class="text-secondary mb-3">Primary Address</h4>
                <div class="profile-detail">
                    <p><strong>Address 1:</strong> <?= htmlspecialchars($customerData['address1'] ?? 'N/A'); ?></p>
                    <p><strong>Address 2:</strong> <?= htmlspecialchars($customerData['address2'] ?? ''); ?></p>
                    <p><strong>Parish:</strong> <?= htmlspecialchars($customerData['parish'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="row mt-4 pt-3 border-top">
             <div class="col-12">
                <p class="mb-0">
                    <strong>Account Status:</strong> 
                    <span class="status-active"><?= $accountStatus; ?></span>
                </p>
            </div>
        </div>
    </div>
    
    <div class="profile-card">
        <ul class="nav nav-tabs" id="customerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab" aria-controls="orders" aria-selected="true">
                    <span class="material-symbols-outlined align-middle" style="font-size: 18px;">receipt_long</span> Orders (<?= count($orders) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                    <span class="material-symbols-outlined align-middle" style="font-size: 18px;">history</span> Activity Log
                </button>
            </li>
        </ul>
        <div class="tab-content p-3 border border-top-0" id="customerTabsContent">
            
            <div class="tab-pane fade show active" id="orders" role="tabpanel" aria-labelledby="orders-tab">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Order #</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Delivery Method</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): 
                                    $statusClass = match(strtolower($order['status'])) {
                                        'delivered', 'completed' => 'text-success',
                                        'pending', 'processing' => 'text-warning',
                                        'cancelled' => 'text-danger',
                                        default => 'text-info',
                                    };
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($order['orderId']); ?></td>
                                        <td><?= date("Y-m-d", strtotime($order['orderDate'])); ?></td>
                                        <td>$<?= number_format($order['totalAmount'], 2); ?></td>
                                        <td><?= htmlspecialchars($order['deliveryMethod']); ?></td>
                                        <td class="<?= $statusClass ?>">
                                            <?= htmlspecialchars(ucfirst($order['status'])); ?>
                                        </td>
                                        <td>
                                            <a href="viewCustomerOrderDetails.php?id=<?= $order['orderId']; ?>" class="btn btn-sm btn-outline-info">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4 text-muted">No recent orders found for this customer.</div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                <p class="text-muted">Activity log feature to be implemented here (e.g., login history, profile changes).</p>
                <ul class="list-unstyled">
                    <li><small class="text-muted">Placeholder: Account created on <?= date("Y-m-d", strtotime($customerData['created_at'])); ?></small></li>
                </ul>
            </div>
            
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>