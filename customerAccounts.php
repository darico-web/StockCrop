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

// Assuming roleId = 3 is for standard Customers/Clients
$customerRoleId = 3; 

// --- 1. Handle Search Input ---
$search = trim($_GET['search'] ?? '');
$searchQuery = "%" . $search . "%"; 
$whereClause = "u.roleId = ?";
$params = "i";
$paramValues = [$customerRoleId];

if (!empty($search)) {
    // Add filtering to the WHERE clause
    $whereClause .= " AND (
        c.firstName LIKE ? OR 
        c.lastName LIKE ? OR 
        u.email LIKE ? OR
        c.parish LIKE ?
    )";
    $params .= "ssss";
    $paramValues[] = $searchQuery;
    $paramValues[] = $searchQuery;
    $paramValues[] = $searchQuery;
    $paramValues[] = $searchQuery;
}

// --- 2. Fetch Customer Accounts ---
$query = "
    SELECT 
        u.id AS userId,
        u.email,
        u.created_at,
        c.firstName,
        c.lastName,
        c.phoneNumber,
        c.parish
    FROM users u
    LEFT JOIN customers c ON u.id = c.userId
    WHERE {$whereClause}
    ORDER BY u.created_at DESC
";

$stmt = mysqli_prepare($conn, $query);

// Bind parameters dynamically
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, $params, ...$paramValues);
} else {
    // Only bind roleId if no search is performed
    mysqli_stmt_bind_param($stmt, $params, $customerRoleId);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    error_log("Customer query failed: " . mysqli_error($conn));
    $customerData = [];
} else {
    $customerData = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
mysqli_stmt_close($stmt);

$customerCount = count($customerData);
$totalCustomers = $customerCount; // Quick display count for the result set
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Accounts | StockCrop Admin</title>
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

        /* Card Styling for Customers */
        .customer-card {
            background: white;
            border-left: 5px solid var(--primary-green);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .customer-card-header {
            border-bottom: 1px dashed #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .customer-card-body p {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .customer-card-footer {
            margin-top: auto; /* Pushes the button to the bottom */
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0">Customer Accounts 🧑‍🤝‍🧑</h2>
            <?php if (!empty($search)): ?>
                <p class="text-muted">Found <?= $totalCustomers ?> results for "<?= htmlspecialchars($search); ?>"</p>
            <?php else: ?>
                <p class="text-muted">Total registered customers: <?= $totalCustomers ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-end">
            <a href="addCustomerAccount.php" class="btn btn-primary btn-lg">
                <span class="material-symbols-outlined align-middle me-1">person_add</span>
                Add New Customer
            </a>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-12">
            <form method="GET" action="customerAccounts.php">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0">
                        <span class="material-symbols-outlined text-muted">search</span>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by Name, Email, or Parish..." value="<?= htmlspecialchars($search); ?>">
                    <?php if (!empty($search)): ?>
                        <a href="customerAccounts.php" class="btn btn-outline-secondary" title="Clear Search">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success">Search</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row g-4">
        <?php if ($customerCount > 0): ?>
            <?php foreach ($customerData as $customer): 
                
                $fullName = htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']);
                $fullName = trim($fullName) ?: htmlspecialchars($customer['email']);

                // Since u.status is removed, we hardcode status as Active for display
                $statusBadge = '<span class="badge bg-success ms-2">Active</span>';
            ?>
                <div class="col-xl-4 col-lg-6 col-md-12">
                    <div class="customer-card">
                        <div class="customer-card-header d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0">
                                <?= $fullName; ?>
                            </h5>
                            <?= $statusBadge; ?>
                        </div>

                        <div class="customer-card-body">
                            <p class="text-muted">User ID: #<?= $customer['userId']; ?></p>
                            
                            <p>
                                <span class="material-symbols-outlined align-middle" style="font-size: 16px;">mail</span>
                                <?= htmlspecialchars($customer['email']); ?>
                            </p>
                            <p>
                                <span class="material-symbols-outlined align-middle" style="font-size: 16px;">call</span>
                                <?= htmlspecialchars($customer['phoneNumber'] ?? 'N/A'); ?>
                            </p>
                            <p>
                                <span class="material-symbols-outlined align-middle" style="font-size: 16px;">location_city</span>
                                <?= htmlspecialchars($customer['parish'] ?? 'N/A'); ?>
                            </p>
                            <p>
                                <span class="material-symbols-outlined align-middle" style="font-size: 16px;">schedule</span>
                                Joined: <?= date("M j, Y", strtotime($customer['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div class="customer-card-footer">
                            <a href="viewCustomerProfileAndOrders.php?id=<?= $customer['userId']; ?>" class="btn btn-sm btn-outline-success w-100">
                                View Profile & Orders
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning text-center p-5">
                    <span class="material-symbols-outlined text-warning" style="font-size: 48px;">person_search</span>
                    <h4 class="mt-3">No Customers Found</h4>
                    <p>No customer accounts match the search term "<?= htmlspecialchars($search); ?>".</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>