<?php
session_start();
include 'config.php';
include 'session.php'; 

// Only allow ADMIN (roleId = 1)
redirectIfNotLoggedIn();
if ($_SESSION['roleId'] != 1) {
    exit();
}

$orderId = (int)$_GET['id'] ?? 0;

if ($orderId === 0) {
    echo "<h1>Error: No Order ID specified.</h1>";
    exit();
}

$orderHeader = null;
$orderItems = [];

// --- 1. Fetch Order Header and Customer Details ---
$headerQuery = "
    SELECT 
        o.id AS orderId,
        o.orderDate,
        o.deliveryMethod,
        o.deliveryAddress,
        o.recipientPhone,
        c.firstName AS custFirstName,
        c.lastName AS custLastName,
        c.parish,
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
} else {
    echo "<h1>Error: Order not found.</h1>";
    exit();
}
mysqli_stmt_close($stmt);

// --- 2. Fetch Order Items (Consolidated by Farmer/Supplier) ---
$itemsQuery = "
    SELECT 
        p.productName,
        oi.quantity,
        p.unitOfSale,
        f.firstName AS farmerFirstName,
        f.lastName AS farmerLastName
    FROM order_items oi
    JOIN products p ON oi.productId = p.id
    JOIN farmers f ON oi.farmerId = f.id
    WHERE oi.orderId = ?
    ORDER BY f.lastName, p.productName
";

$stmt = mysqli_prepare($conn, $itemsQuery);
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$itemsResult = mysqli_stmt_get_result($stmt);

if ($itemsResult) {
    $orderItems = mysqli_fetch_all($itemsResult, MYSQLI_ASSOC);
}
mysqli_stmt_close($stmt);

$customerName = htmlspecialchars($orderHeader['custFirstName'] . ' ' . $orderHeader['custLastName']);
$currentFarmer = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Packing Slip #<?= $orderId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: white; }
        .container { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #000; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .items-table td { padding: 8px 5px; }
        .farmer-row { background-color: #e6f7e9; font-weight: bold; }
        .address-box { border: 1px dashed #666; padding: 10px; margin-bottom: 20px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="mb-1">PACKING SLIP / DELIVERY NOTE</h1>
        <div class="d-flex justify-content-between">
            <p class="mb-0">Order #<?= $orderId ?> | Date: <?= date("Y-m-d", strtotime($orderHeader['orderDate'])) ?></p>
            <p class="mb-0 fw-bold">DELIVERY METHOD: <?= strtoupper($orderHeader['deliveryMethod']) ?></p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <h5>DELIVERY TO:</h5>
            <div class="address-box">
                <p class="mb-1 fw-bold fs-5"><?= $customerName ?></p>
                <?php if ($orderHeader['deliveryMethod'] == 'Delivery'): ?>
                    <p class="mb-1"><?= htmlspecialchars($orderHeader['deliveryAddress']); ?></p>
                    <p class="mb-1"><?= htmlspecialchars($orderHeader['parish']); ?></p>
                <?php else: ?>
                    <p class="mb-1">Customer Pickup (No Delivery Address Required)</p>
                <?php endif; ?>
                <p class="mb-0">Phone: <?= htmlspecialchars($orderHeader['recipientPhone']); ?></p>
            </div>
        </div>
    </div>
    
    <h5 class="mt-4">ITEMS CONSOLIDATED FOR THIS ORDER:</h5>
    <table class="table items-table">
        <thead>
            <tr>
                <th style="width: 40%;">Product</th>
                <th style="width: 25%;">Supplier (Farmer)</th>
                <th style="width: 15%;">Quantity</th>
                <th style="width: 20%;">Unit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderItems as $item): ?>
                <?php 
                $farmerFullName = htmlspecialchars($item['farmerFirstName'] . ' ' . $item['farmerLastName']);
                if ($currentFarmer != $farmerFullName) {
                    $currentFarmer = $farmerFullName;
                    echo '<tr class="farmer-row"><td colspan="4">From Farmer: ' . $currentFarmer . '</td></tr>';
                }
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['productName']); ?></td>
                    <td></td>
                    <td><?= $item['quantity']; ?></td>
                    <td><?= htmlspecialchars($item['unitOfSale']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="no-print mt-5 text-center">
        <button onclick="window.print()" class="btn btn-success btn-lg me-3">
            <span class="material-symbols-outlined align-middle me-1">print</span> Print Document
        </button>
        <a href="orderProcessing.php" class="btn btn-secondary">
            <span class="material-symbols-outlined align-middle me-1">arrow_back</span> Back
        </a>
    </div>

</div>

</body>
</html>