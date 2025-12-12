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

// --- 1. Fetch Products, Categories, and Farmers ---
$query = "
    SELECT 
        p.id AS productId,
        p.productName,
        p.price,
        p.unitOfSale,
        p.stockQuantity,
        p.imagePath,
        c.categoryName, -- CORRECTED: Using c.categoryName as the alias is redundant but safe
        CONCAT(f.firstName, ' ', f.lastName) AS farmerName
    FROM products p
    LEFT JOIN categories c ON p.categoryId = c.id
    LEFT JOIN farmers f ON p.farmerId = f.id
    ORDER BY p.productName ASC
";

$result = mysqli_query($conn, $query);

// Check for query errors
if (!$result) {
    error_log("Product query failed: " . mysqli_error($conn));
    $productsData = [];
} else {
    $productsData = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

$productCount = count($productsData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Inventory | StockCrop Admin</title>
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

        .data-table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .stock-low {
            background-color: #fff3cd; 
            color: #856404;
            font-weight: bold;
        }
        
        .stock-ok {
            color: var(--primary-green);
            font-weight: bold;
        }
        
        .table-action-col {
            width: 100px;
            text-align: center;
        }

        .product-image-preview {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 10px;
        }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0">Product Inventory 🌾</h2>
            <p class="text-muted">Total unique products listed: <?= $productCount ?></p>
        </div>
        <div class="col-md-6 text-end">
            <a href="adminAddProduct.php" class="btn btn-success btn-lg">
                <span class="material-symbols-outlined align-middle me-1">add_circle</span>
                Add New Product
            </a>
        </div>
    </div>

    <div class="data-table-card">
        
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col"># ID</th>
                        <th scope="col">Product Name</th>
                        <th scope="col">Category</th>
                        <th scope="col">Price / Unit</th>
                        <th scope="col">Stock Level</th>
                        <th scope="col">Listed By</th>
                        <th scope="col" class="table-action-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($productCount > 0): ?>
                        <?php foreach ($productsData as $product): 
                            // Determine stock status for conditional styling
                            $stockLevel = $product['stockQuantity'];
                            $stockClass = '';
                            if ($stockLevel <= 10 && $stockLevel > 0) {
                                $stockClass = 'text-warning fw-bold';
                            } elseif ($stockLevel == 0) {
                                $stockClass = 'text-danger fw-bold';
                            } else {
                                $stockClass = 'stock-ok';
                            }
                            $imageSrc = !empty($product['imagePath']) ? htmlspecialchars($product['imagePath']) : 'assets/default_product.png'; 
                        ?>
                            <tr>
                                <th scope="row"><?= htmlspecialchars($product['productId']); ?></th>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= $imageSrc; ?>" alt="<?= htmlspecialchars($product['productName']); ?>" class="product-image-preview">
                                        <?= htmlspecialchars($product['productName']); ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($product['categoryName'] ?? 'N/A'); ?></td>
                                <td>$<?= number_format($product['price'], 2); ?> / <?= htmlspecialchars($product['unitOfSale']); ?></td>
                                <td class="<?= $stockClass ?>">
                                    <?= htmlspecialchars($stockLevel); ?>
                                    <?php if ($stockLevel == 0): ?>
                                        <span class="badge bg-danger ms-1">OUT OF STOCK</span>
                                    <?php elseif ($stockLevel <= 10): ?>
                                        <span class="badge bg-warning text-dark ms-1">LOW STOCK</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($product['farmerName'] ?? 'N/A'); ?></small>
                                </td>
                                <td class="table-action-col">
                                    <a href="inventoryEditProduct?id=<?= $product['productId']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Product">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">edit</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center p-5">
                                <span class="material-symbols-outlined text-muted" style="font-size: 48px;">inventory_2_off</span>
                                <h4 class="text-muted mt-3">Inventory is empty.</h4>
                                <p class="text-secondary">Start by adding your first product to the database.</p>
                                <a href="addProduct.php" class="btn btn-success mt-2">Add New Product</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>