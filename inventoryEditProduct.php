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

$productId = (int)$_GET['id'] ?? 0;
$product = null;
$categories = [];
$farmers = [];
$errors = [];
$successMessage = '';

// 1. INPUT VALIDATION & INITIAL DATA FETCH
if ($productId === 0) {
    header("Location: inventory.php");
    exit();
}

// --- Fetch Categories and Farmers (for dropdowns) ---
$categoriesResult = mysqli_query($conn, "SELECT id, categoryName FROM categories ORDER BY categoryName ASC");
if ($categoriesResult) {
    $categories = mysqli_fetch_all($categoriesResult, MYSQLI_ASSOC);
    mysqli_free_result($categoriesResult);
}

$farmersResult = mysqli_query($conn, "SELECT id, firstName, lastName FROM farmers ORDER BY lastName ASC");
if ($farmersResult) {
    $farmers = mysqli_fetch_all($farmersResult, MYSQLI_ASSOC);
    mysqli_free_result($farmersResult);
}

// --- Fetch the Product's current data ---
$stmt = mysqli_prepare($conn, "
    SELECT 
        id AS productId,
        farmerId,
        categoryId,
        productName,
        description,
        price,
        unitOfSale,
        stockQuantity,
        imagePath
    FROM products
    WHERE id = ?
");
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $product = $row;
} else {
    // Product not found
    header("Location: inventory.php");
    exit();
}
mysqli_stmt_close($stmt);


// 2. HANDLE FORM SUBMISSION (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize and validate inputs
    $formData = [
        'productName'   => trim($_POST['productName'] ?? ''),
        'description'   => trim($_POST['description'] ?? ''),
        'price'         => floatval($_POST['price'] ?? 0),
        'unitOfSale'    => trim($_POST['unitOfSale'] ?? ''),
        'stockQuantity' => intval($_POST['stockQuantity'] ?? 0),
        'categoryId'    => intval($_POST['categoryId'] ?? 0),
        'farmerId'      => intval($_POST['farmerId'] ?? 0),
        'existingImagePath' => $_POST['existingImagePath'] ?? $product['imagePath']
    ];

    // Basic Validation Checks
    if (empty($formData['productName'])) $errors['productName'] = "Product Name is required.";
    if ($formData['price'] <= 0) $errors['price'] = "Price must be greater than zero.";
    if (empty($formData['unitOfSale'])) $errors['unitOfSale'] = "Unit of Sale (e.g., lb, dozen) is required.";
    if ($formData['stockQuantity'] < 0) $errors['stockQuantity'] = "Stock Quantity cannot be negative.";
    if ($formData['categoryId'] <= 0) $errors['categoryId'] = "Category is required.";
    if ($formData['farmerId'] <= 0) $errors['farmerId'] = "Farmer is required.";
    
    // --- Handle Image Upload (if a new file is uploaded) ---
    $newImagePath = $formData['existingImagePath'];
    
    if (isset($_FILES['imagePath']) && $_FILES['imagePath']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/product_images/'; // Define your upload directory
        $fileName = basename($_FILES['imagePath']['name']);
        $targetFilePath = $uploadDir . time() . '_' . $fileName;
        $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        // Check file type
        if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            $errors['imagePath'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        } else if ($_FILES['imagePath']['size'] > 5000000) { // 5MB limit
            $errors['imagePath'] = "Sorry, your file is too large (max 5MB).";
        } else {
            // Attempt to upload file
            if (move_uploaded_file($_FILES['imagePath']['tmp_name'], $targetFilePath)) {
                $newImagePath = $targetFilePath;
                // Optionally delete the old image if it exists and is not the default
                if (!empty($formData['existingImagePath']) && file_exists($formData['existingImagePath']) && $formData['existingImagePath'] !== 'assets/default_product.png') {
                    unlink($formData['existingImagePath']);
                }
            } else {
                $errors['imagePath'] = "Error uploading file.";
            }
        }
    }


    if (empty($errors)) {
        // Prepare the SQL UPDATE statement
        $updateQuery = "
            UPDATE products SET
                productName = ?,
                description = ?,
                price = ?,
                unitOfSale = ?,
                stockQuantity = ?,
                categoryId = ?,
                farmerId = ?,
                imagePath = ?
            WHERE id = ?
        ";
        
        $stmt = mysqli_prepare($conn, $updateQuery);
        
        // Bind parameters: (2 strings, 1 double, 1 string, 3 integers, 1 string, 1 integer)
        mysqli_stmt_bind_param($stmt, "ssdsiiisi", 
            $formData['productName'], 
            $formData['description'], 
            $formData['price'], 
            $formData['unitOfSale'], 
            $formData['stockQuantity'], 
            $formData['categoryId'], 
            $formData['farmerId'], 
            $newImagePath,
            $productId
        );

        if (mysqli_stmt_execute($stmt)) {
            $successMessage = "Product **" . htmlspecialchars($formData['productName']) . "** updated successfully! Redirecting...";
            
            // Re-fetch the data to update the form fields and show the new path
            $product = array_merge($product, $formData);
            $product['imagePath'] = $newImagePath;

            // Redirect after a slight delay for better UX
            header("Refresh: 2; URL=inventory.php"); // Redirect back to inventory list
            
        } else {
            $errors['db'] = "Database update failed: " . mysqli_error($conn);
        }

        mysqli_stmt_close($stmt);
    }
    
    // If validation failed, merge POST data with the product array to keep inputs filled
    if (!empty($errors)) {
        $product = array_merge($product, $formData);
    }
}

// Data to pre-fill the form (uses fetched or POSTed data)
$formData = $product;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product: <?= htmlspecialchars($product['productName']); ?> | StockCrop Admin</title>
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

        .edit-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(47, 143, 63, 0.25);
        }

        .image-preview-container {
            width: 150px;
            height: 150px;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }

        .image-preview-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-12">
            <h2 class="fw-bold mb-0">
                <a href="productInventory.php" class="text-secondary text-decoration-none me-2">&leftarrow;</a>
                Edit Product: <?= htmlspecialchars($product['productName']); ?>
            </h2>
            <p class="text-muted">Product ID: #<?= $productId ?> | Last Updated: <?php // Add updated_at column to table to display last updated time ?></p>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span class="material-symbols-outlined align-middle me-1">check_circle</span>
            **Success!** <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <span class="material-symbols-outlined align-middle me-1">error</span>
            **Error!** Please correct the following issues:
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="edit-card">
        <form method="POST" action="editProduct.php?id=<?= $productId; ?>" enctype="multipart/form-data">
            
            <input type="hidden" name="existingImagePath" value="<?= htmlspecialchars($formData['imagePath'] ?? ''); ?>">

            <h4 class="mb-4 text-secondary">Product Details</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label for="productName" class="form-label">Product Name *</label>
                    <input type="text" class="form-control <?= isset($errors['productName']) ? 'is-invalid' : '' ?>" id="productName" name="productName" value="<?= htmlspecialchars($formData['productName'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['productName'] ?? '' ?></div>
                </div>
                <div class="col-md-4">
                    <label for="categoryId" class="form-label">Category *</label>
                    <select class="form-select <?= isset($errors['categoryId']) ? 'is-invalid' : '' ?>" id="categoryId" name="categoryId" required>
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id']; ?>" 
                                <?= (isset($formData['categoryId']) && $formData['categoryId'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['categoryName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= $errors['categoryId'] ?? '' ?></div>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description (Optional)</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <h4 class="mb-4 text-secondary">Pricing & Stock</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="price" class="form-label">Unit Price ($) *</label>
                    <input type="number" step="0.01" min="0.01" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" id="price" name="price" value="<?= htmlspecialchars($formData['price'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['price'] ?? '' ?></div>
                </div>
                <div class="col-md-4">
                    <label for="unitOfSale" class="form-label">Unit of Sale (e.g., lb, doz) *</label>
                    <input type="text" class="form-control <?= isset($errors['unitOfSale']) ? 'is-invalid' : '' ?>" id="unitOfSale" name="unitOfSale" value="<?= htmlspecialchars($formData['unitOfSale'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['unitOfSale'] ?? '' ?></div>
                </div>
                <div class="col-md-4">
                    <label for="stockQuantity" class="form-label">Stock Quantity *</label>
                    <input type="number" min="0" class="form-control <?= isset($errors['stockQuantity']) ? 'is-invalid' : '' ?>" id="stockQuantity" name="stockQuantity" value="<?= htmlspecialchars($formData['stockQuantity'] ?? 0); ?>" required>
                    <div class="invalid-feedback"><?= $errors['stockQuantity'] ?? '' ?></div>
                </div>
            </div>

            <h4 class="mb-4 text-secondary">Farmer & Image</h4>
            <div class="row g-3 mb-5">
                <div class="col-md-6">
                    <label for="farmerId" class="form-label">Listed By Farmer *</label>
                    <select class="form-select <?= isset($errors['farmerId']) ? 'is-invalid' : '' ?>" id="farmerId" name="farmerId" required>
                        <option value="">Select Farmer...</option>
                        <?php foreach ($farmers as $farmer): ?>
                            <option value="<?= $farmer['id']; ?>" 
                                <?= (isset($formData['farmerId']) && $formData['farmerId'] == $farmer['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($farmer['firstName'] . ' ' . $farmer['lastName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= $errors['farmerId'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Current Image Preview</label>
                    <div class="image-preview-container">
                        <img id="imagePreview" src="<?= htmlspecialchars($formData['imagePath'] ?? 'assets/default_product.png'); ?>" alt="Product Image">
                    </div>
                    <label for="imagePath" class="form-label">Upload New Image (Optional, max 5MB)</label>
                    <input type="file" class="form-control <?= isset($errors['imagePath']) ? 'is-invalid' : '' ?>" id="imagePath" name="imagePath" accept="image/*">
                    <div class="invalid-feedback"><?= $errors['imagePath'] ?? '' ?></div>
                </div>
            </div>

            <div class="d-flex justify-content-between pt-3 border-top">
                <a href="productInventory.php" class="btn btn-secondary">
                    <span class="material-symbols-outlined align-middle me-1">cancel</span>
                    Cancel / Back to Inventory
                </a>
                <button type="submit" class="btn btn-success btn-lg">
                    <span class="material-symbols-outlined align-middle me-1">save</span>
                    Save Product Changes
                </button>
            </div>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple script to update image preview when a new file is selected
    document.getElementById('imagePath').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
</script>
</body>
</html>