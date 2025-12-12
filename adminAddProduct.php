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

$categories = [];
$farmers = [];
$errors = [];
$successMessage = '';
$formData = []; // Used to retain input values on validation error

// --- 1. Fetch Categories and Farmers (for dropdowns) ---
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

// --- 2. HANDLE FORM SUBMISSION (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize and validate inputs
    $formData = [
        'productName'   => trim($_POST['productName'] ?? ''),
        'description'   => trim($_POST['description'] ?? ''),
        'price'         => floatval($_POST['price'] ?? 0),
        'unitOfSale'    => trim($_POST['unitOfSale'] ?? ''),
        'stockQuantity' => intval($_POST['stockQuantity'] ?? 0),
        'categoryId'    => intval($_POST['categoryId'] ?? 0),
        'farmerId'      => intval($_POST['farmerId'] ?? 0)
    ];

    // Basic Validation Checks
    if (empty($formData['productName'])) $errors['productName'] = "Product Name is required.";
    if ($formData['price'] <= 0) $errors['price'] = "Price must be greater than zero.";
    if (empty($formData['unitOfSale'])) $errors['unitOfSale'] = "Unit of Sale (e.g., lb, dozen) is required.";
    if ($formData['stockQuantity'] < 0) $errors['stockQuantity'] = "Stock Quantity cannot be negative.";
    if ($formData['categoryId'] <= 0) $errors['categoryId'] = "Category is required.";
    if ($formData['farmerId'] <= 0) $errors['farmerId'] = "Farmer is required.";
    
    // --- Handle Image Upload ---
    $newImagePath = 'assets/default_product.png'; // Default image path

    if (isset($_FILES['imagePath']) && $_FILES['imagePath']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/product_images/'; // Define your upload directory
        
        // Ensure the upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($_FILES['imagePath']['name']);
        // Create a unique file name to prevent overwrites
        $targetFilePath = $uploadDir . time() . '_' . $fileName; 
        $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        // Check file type and size
        if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            $errors['imagePath'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        } else if ($_FILES['imagePath']['size'] > 5000000) { // 5MB limit
            $errors['imagePath'] = "Sorry, your file is too large (max 5MB).";
        } else {
            // Attempt to upload file
            if (move_uploaded_file($_FILES['imagePath']['tmp_name'], $targetFilePath)) {
                $newImagePath = $targetFilePath;
            } else {
                $errors['imagePath'] = "Error uploading file.";
            }
        }
    }


    if (empty($errors)) {
        // Prepare the SQL INSERT statement
        $insertQuery = "
            INSERT INTO products (
                productName, description, price, unitOfSale, stockQuantity, categoryId, farmerId, imagePath
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = mysqli_prepare($conn, $insertQuery);
        
        // Bind parameters: (2 strings, 1 double, 1 string, 3 integers, 1 string)
        mysqli_stmt_bind_param($stmt, "ssdsiiis", 
            $formData['productName'], 
            $formData['description'], 
            $formData['price'], 
            $formData['unitOfSale'], 
            $formData['stockQuantity'], 
            $formData['categoryId'], 
            $formData['farmerId'], 
            $newImagePath
        );

        if (mysqli_stmt_execute($stmt)) {
            $newProductId = mysqli_insert_id($conn);
            $successMessage = "Product **" . htmlspecialchars($formData['productName']) . "** added successfully! Redirecting...";
            
            // Clear form data on success
            $formData = [];

            // Redirect after a slight delay for better UX
            header("Refresh: 2; URL=inventory.php"); // Redirect back to inventory list
            
        } else {
            $errors['db'] = "Database insertion failed: " . mysqli_error($conn);
            // If the insertion failed, but an image was uploaded, delete it to prevent clutter
            if ($newImagePath !== 'assets/default_product.png' && file_exists($newImagePath)) {
                unlink($newImagePath);
            }
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Product | StockCrop Admin</title>
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

        .add-card {
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
                <a href="inventory.php" class="text-secondary text-decoration-none me-2">&leftarrow;</a>
                Add New Product 🍎
            </h2>
            <p class="text-muted">Enter the details for the new product to add it to the inventory.</p>
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

    <div class="add-card">
        <form method="POST" action="addProduct.php" enctype="multipart/form-data">
            
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
                    <label class="form-label">Image Preview (Default)</label>
                    <div class="image-preview-container">
                        <img id="imagePreview" src="assets/default_product.png" alt="Product Image">
                    </div>
                    <label for="imagePath" class="form-label">Product Image (Optional, max 5MB)</label>
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
                    <span class="material-symbols-outlined align-middle me-1">add_circle</span>
                    Add Product to Inventory
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
        } else {
            // Revert to default image if file selection is cancelled
            document.getElementById('imagePreview').src = 'assets/default_product.png';
        }
    });
</script>
</body>
</html>