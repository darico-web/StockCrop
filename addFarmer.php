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

$errors = [];
$successMessage = '';
$formData = []; // Array to hold user input if submission fails

// Define available parishes
$parishes = [
    'Clarendon', 'Hanover', 'Kingston', 'Manchester', 'Portland', 'St. Andrew', 
    'St. Ann', 'St. Catherine', 'St. Elizabeth', 'St. James', 'St. Mary', 
    'St. Thomas', 'Trelawny', 'Westmoreland'
];

// --- 1. HANDLE FORM SUBMISSION (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize inputs
    $formData['firstName'] = trim($_POST['firstName'] ?? '');
    $formData['lastName'] = trim($_POST['lastName'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phoneNumber'] = trim($_POST['phoneNumber'] ?? '');
    $formData['radaIdNumber'] = trim($_POST['radaIdNumber'] ?? '');
    $formData['address1'] = trim($_POST['address1'] ?? '');
    $formData['address2'] = trim($_POST['address2'] ?? '');
    $formData['parish'] = trim($_POST['parish'] ?? '');
    $formData['password'] = $_POST['password'] ?? ''; // New account requires a temporary password
    $formData['confirm_password'] = $_POST['confirm_password'] ?? '';

    // --- Basic Validation Checks ---
    if (empty($formData['firstName'])) $errors['firstName'] = "First Name is required.";
    if (empty($formData['lastName'])) $errors['lastName'] = "Last Name is required.";
    if (empty($formData['phoneNumber'])) $errors['phoneNumber'] = "Phone Number is required.";
    if (empty($formData['radaIdNumber'])) $errors['radaIdNumber'] = "RADA ID is required.";
    if (empty($formData['parish'])) $errors['parish'] = "Parish is required.";
    
    // Email Validation
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "A valid Email is required.";
    }

    // Password Validation
    if (empty($formData['password'])) {
        $errors['password'] = "A temporary password is required for the new account.";
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = "Passwords do not match.";
    } elseif (strlen($formData['password']) < 6) {
        $errors['password'] = "Password must be at least 6 characters long.";
    }

    // --- Complex Validation: Check for existing RADA ID or Email ---
    if (empty($errors)) {
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM farmers WHERE radaIdNumber = ? OR email = ?");
        mysqli_stmt_bind_param($checkStmt, "ss", $formData['radaIdNumber'], $formData['email']);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors['duplicate'] = "A farmer with this RADA ID or Email already exists.";
        }
        mysqli_stmt_close($checkStmt);
    }


    if (empty($errors)) {
        
        // --- 2. CREATE USER ACCOUNT (in users table) ---
        // Farmers log in, so they must have an entry in the users table (roleId = 2)
        $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
        $roleId = 2; // Assuming 2 is the roleId for a farmer/client account

        $userStmt = mysqli_prepare($conn, "INSERT INTO users (email, password, roleId) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($userStmt, "ssi", $formData['email'], $hashedPassword, $roleId);
        
        if (mysqli_stmt_execute($userStmt)) {
            $newUserId = mysqli_insert_id($conn);
            mysqli_stmt_close($userStmt);
            
            // --- 3. CREATE FARMER PROFILE (in farmers table) ---
            $farmerQuery = "
                INSERT INTO farmers (
                    userId, firstName, lastName, email, phoneNumber, radaIdNumber, address1, address2, parish, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $farmerStmt = mysqli_prepare($conn, $farmerQuery);
            mysqli_stmt_bind_param($farmerStmt, "issssssss", 
                $newUserId,
                $formData['firstName'], 
                $formData['lastName'], 
                $formData['email'], 
                $formData['phoneNumber'], 
                $formData['radaIdNumber'], 
                $formData['address1'], 
                $formData['address2'], 
                $formData['parish']
            );

            if (mysqli_stmt_execute($farmerStmt)) {
                $newFarmerId = mysqli_insert_id($conn);
                $successMessage = "Farmer **" . htmlspecialchars($formData['firstName'] . ' ' . $formData['lastName']) . "** registered successfully! Redirecting to profile...";
                
                // Clear form data after success
                $formData = []; 
                // Redirect to the new farmer's view profile page
                header("Refresh: 3; URL=viewFarmer.php?id=$newFarmerId");
                
            } else {
                // If farmer insertion fails, ideally delete the users entry to clean up
                // NOTE: For simplicity here, we'll just log the error.
                $errors['db'] = "Farmer profile insertion failed: " . mysqli_error($conn);
            }
            mysqli_stmt_close($farmerStmt);
            
        } else {
            $errors['db'] = "User account creation failed: " . mysqli_error($conn);
        }
    }
}
// If form submission failed (due to validation or DB error), $formData retains the input to pre-fill the form.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Farmer | StockCrop Admin</title>
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

        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(47, 143, 63, 0.25);
        }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-12">
            <h2 class="fw-bold mb-0">
                <a href="farmManagement.php" class="text-secondary text-decoration-none me-2">&leftarrow;</a>
                Register New Farmer 🧑‍🌾
            </h2>
            <p class="text-muted">Fill out the form below to create a new farmer profile and user account.</p>
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

    <div class="form-card">
        <form method="POST" action="addFarmer.php">
            
            <h4 class="mb-4 text-secondary">Personal & Contact Details</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="firstName" class="form-label">First Name *</label>
                    <input type="text" class="form-control <?= isset($errors['firstName']) ? 'is-invalid' : '' ?>" id="firstName" name="firstName" value="<?= htmlspecialchars($formData['firstName'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['firstName'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="lastName" class="form-label">Last Name *</label>
                    <input type="text" class="form-control <?= isset($errors['lastName']) ? 'is-invalid' : '' ?>" id="lastName" name="lastName" value="<?= htmlspecialchars($formData['lastName'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['lastName'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['email'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="phoneNumber" class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control <?= isset($errors['phoneNumber']) ? 'is-invalid' : '' ?>" id="phoneNumber" name="phoneNumber" value="<?= htmlspecialchars($formData['phoneNumber'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['phoneNumber'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="radaIdNumber" class="form-label">RADA ID Number *</label>
                    <input type="text" class="form-control <?= isset($errors['radaIdNumber']) ? 'is-invalid' : '' ?>" id="radaIdNumber" name="radaIdNumber" value="<?= htmlspecialchars($formData['radaIdNumber'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['radaIdNumber'] ?? '' ?></div>
                </div>
            </div>

            <h4 class="mb-4 text-secondary">Location Details</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="parish" class="form-label">Parish *</label>
                    <select class="form-select <?= isset($errors['parish']) ? 'is-invalid' : '' ?>" id="parish" name="parish" required>
                        <option value="">Choose...</option>
                        <?php foreach ($parishes as $p): ?>
                            <option value="<?= htmlspecialchars($p); ?>" 
                                <?= (isset($formData['parish']) && $formData['parish'] == $p) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($p); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= $errors['parish'] ?? '' ?></div>
                </div>
                <div class="col-md-4">
                    <label for="address1" class="form-label">Address Line 1</label>
                    <input type="text" class="form-control" id="address1" name="address1" value="<?= htmlspecialchars($formData['address1'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="address2" class="form-label">Address Line 2 (Optional)</label>
                    <input type="text" class="form-control" id="address2" name="address2" value="<?= htmlspecialchars($formData['address2'] ?? ''); ?>">
                </div>
            </div>
            
            <h4 class="mb-4 text-secondary">Account Credentials (Temporary)</h4>
            <div class="alert alert-info small" role="alert">
                A temporary password is required to create the farmer's login account. The farmer should change this immediately upon first login.
            </div>
            <div class="row g-3 mb-5">
                <div class="col-md-6">
                    <label for="password" class="form-label">Temporary Password *</label>
                    <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" required>
                    <div class="invalid-feedback"><?= $errors['password'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" required>
                    <div class="invalid-feedback"><?= $errors['confirm_password'] ?? '' ?></div>
                </div>
            </div>

            <div class="d-flex justify-content-between pt-3 border-top">
                <a href="farmers.php" class="btn btn-secondary">
                    <span class="material-symbols-outlined align-middle me-1">cancel</span>
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <span class="material-symbols-outlined align-middle me-1">person_add</span>
                    Create Farmer Account
                </button>
            </div>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>