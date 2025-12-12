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

$orderId = (int)$_GET['id'] ?? 0;
$orderHeader = null;
$orderItems = [];
$customerUserId = null;

if ($orderId === 0) {
    header("Location: dashboard.php"); // Redirect to a safe page if no ID is present
    exit();
}

// --- 1. Fetch Order Header and Customer/User IDs ---
// Joins orders, customers, and users to get all necessary details
$headerQuery = "
    SELECT 
        o.id AS orderId,
        o.orderDate,
        o.totalAmount,
        o.shippingFee,
        o.deliveryMethod,
        o.deliveryAddress,
        o.recipientPhone,
        o.paymentMethod,
        o.status,
        c.firstName AS custFirstName,
        c.lastName AS custLastName,
        c.parish,
        u.id AS customerUserId,
        u.email
    FROM orders o
    JOIN customers c ON o.customerId = c.id
    JOIN users u ON c.userId = u.id
    WHERE o.id = ?
";

$stmt = mysqli_prepare($conn, $headerQuery);
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $orderHeader = $row;
    $customerUserId = $row['customerUserId'];
} else {
    // Order not found
    header("Location: customerAccounts.php");
    exit();
}
mysqli_stmt_close($stmt);

// --- 2. Fetch Order Items ---
// Joins order_items, products, and farmers to show full detail for each item
$itemsQuery = "
    SELECT 
        oi.id AS itemId,
        oi.quantity,
        oi.priceAtPurchase,
        oi.lineTotal,
        oi.status AS itemStatus,
        p.productName,
        p.unitOfSale,
        f.firstName AS farmerFirstName,
        f.lastName AS farmerLastName
    FROM order_items oi
    JOIN products p ON oi.productId = p.id
    JOIN farmers f ON oi.farmerId = f.id
    WHERE oi.orderId = ?
";

$stmt = mysqli_prepare($conn, $itemsQuery);
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$itemsResult = mysqli_stmt_get_result($stmt);

if ($itemsResult) {
    $orderItems = mysqli_fetch_all($itemsResult, MYSQLI_ASSOC);
}
mysqli_stmt_close($stmt);

// Format details
$orderDate = date("M j, Y, g:i A", strtotime($orderHeader['orderDate']));
$customerName = htmlspecialchars($orderHeader['custFirstName'] . ' ' . $orderHeader['custLastName']);

// Determine status badge style
$status = strtolower($orderHeader['status']);
$statusBadge = match($status) {
    'delivered', 'completed' => '<span class="badge bg-success fs-6">Delivered</span>',
    'processing' => '<span class="badge bg-primary fs-6">Processing</span>',
    'pending' => '<span class="badge bg-warning text-dark fs-6">Pending</span>',
    'cancelled' => '<span class="badge bg-danger fs-6">Cancelled</span>',
    default => '<span class="badge bg-secondary fs-6">Unknown</span>',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order #<?= $orderId ?> Details | StockCrop Admin</title>
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
        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 20px;
        }
        .summary-box {
            background-color: #f1f8e9; /* Light Green */
            border-radius: 10px;
            padding: 15px;
        }
        .summary-box h5 {
            color: var(--primary-green);
        }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold mb-0">
                <a href="viewCustomer.php?id=<?= $customerUserId ?>" class="text-secondary text-decoration-none me-2">&leftarrow;</a>
                Order Details: #<?= $orderId ?>
            </h2>
            <p class="text-muted">Placed by <?= $customerName ?> on <?= $orderDate ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="editOrder.php?id=<?= $orderId ?>" class="btn btn-warning">
                <span class="material-symbols-outlined align-middle me-1">edit</span>
                Update Status / Edit
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="order-card">
                <h4 class="text-secondary mb-4">Order Summary & Customer</h4>
                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <h6>Customer Details</h6>
                        <p class="mb-1"><strong>Name:</strong> <a href="viewCustomer.php?id=<?= $customerUserId ?>" class="text-decoration-none"><?= $customerName ?></a></p>
                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($orderHeader['email']); ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($orderHeader['recipientPhone']); ?></p>
                        <p class="mb-1"><strong>Parish:</strong> <?= htmlspecialchars($orderHeader['parish']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Order Status & Payment</h6>
                        <p class="mb-1"><strong>Status:</strong> <?= $statusBadge ?></p>
                        <p class="mb-1"><strong>Date:</strong> <?= $orderDate ?></p>
                        <p class="mb-1"><strong>Payment:</strong> <?= htmlspecialchars($orderHeader['paymentMethod']); ?></p>
                        <p class="mb-1"><strong>Delivery:</strong> <?= htmlspecialchars($orderHeader['deliveryMethod']); ?></p>
                    </div>
                </div>
            </div>

            <div class="order-card">
                <h4 class="text-secondary mb-4">Delivery Information</h4>
                <?php if ($orderHeader['deliveryMethod'] == 'Delivery'): ?>
                    <p class="mb-1">**Shipping Address:**</p>
                    <p class="ms-3 mb-1"><?= htmlspecialchars($orderHeader['deliveryAddress']); ?></p>
                    <p class="ms-3 mb-1"><?= htmlspecialchars($orderHeader['parish']); ?></p>
                <?php else: ?>
                    <div class="alert alert-info py-2">
                        <span class="material-symbols-outlined align-middle me-1">store</span>
                        This order is set for **Customer Pickup**.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="order-card summary-box">
                <h4 class="mb-4">Order Totals</h4>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td>Subtotal (Items)</td>
                        <td class="text-end">$<?= number_format($orderHeader['totalAmount'] - $orderHeader['shippingFee'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Shipping Fee</td>
                        <td class="text-end">$<?= number_format($orderHeader['shippingFee'], 2) ?></td>
                    </tr>
                    <tr class="fw-bold fs-5 border-top border-dark">
                        <td>GRAND TOTAL</td>
                        <td class="text-end">$<?= number_format($orderHeader['totalAmount'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="order-card">
        <h4 class="text-secondary mb-4">Items Included (<?= count($orderItems) ?>)</h4>
        
        <?php if (!empty($orderItems)): ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Supplier (Farmer)</th>
                        <th scope="col">Price / Unit</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Line Total</th>
                        <th scope="col">Item Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): 
                        $itemStatus = strtolower($item['itemStatus']);
                        $itemStatusBadge = match($itemStatus) {
                            'ready' => '<span class="badge bg-success">Ready</span>',
                            'in preparation' => '<span class="badge bg-primary">Prep</span>',
                            default => '<span class="badge bg-secondary">--</span>',
                        };
                    ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($item['productName']); ?></td>
                            <td><?= htmlspecialchars($item['farmerFirstName'] . ' ' . $item['farmerLastName']); ?></td>
                            <td>$<?= number_format($item['priceAtPurchase'], 2) ?> / <?= htmlspecialchars($item['unitOfSale']); ?></td>
                            <td>**<?= $item['quantity']; ?>**</td>
                            <td class="fw-bold">$<?= number_format($item['lineTotal'], 2) ?></td>
                            <td><?= $itemStatusBadge; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-warning">No items found for this order. Data integrity issue.</div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>