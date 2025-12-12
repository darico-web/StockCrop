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

// 1. INPUT VALIDATION & INITIAL DATA FETCH
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: farmers.php");
    exit();
}

$farmerId = (int)$_GET['id'];
$farmer = null;
$errors = [];
$successMessage = '';

// Fetch the farmer's current data
$stmt = mysqli_prepare($conn, "
    SELECT 
        id AS farmerId,
        firstName,
        lastName,
        email,
        phoneNumber,
        radaIdNumber,
        address1,
        address2,
        parish,
        userId
    FROM farmers
    WHERE id = ?
");
mysqli_stmt_bind_param($stmt, "i", $farmerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    // Store original data
    $farmer = $row;
} else {
    // Farmer not found
    header("Location: farmers.php");
    exit();
}
mysqli_stmt_close($stmt);


// 2. HANDLE FORM SUBMISSION (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $radaIdNumber = trim($_POST['radaIdNumber'] ?? '');
    $address1 = trim($_POST['address1'] ?? '');
    $address2 = trim($_POST['address2'] ?? '');
    $parish = trim($_POST['parish'] ?? '');

    // Basic Validation Checks
    if (empty($firstName)) $errors['firstName'] = "First Name is required.";
    if (empty($lastName)) $errors['lastName'] = "Last Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "A valid Email is required.";
    if (empty($phoneNumber)) $errors['phoneNumber'] = "Phone Number is required.";
    if (empty($radaIdNumber)) $errors['radaIdNumber'] = "RADA ID is required.";
    if (empty($parish)) $errors['parish'] = "Parish is required.";
    
    // NOTE: You might add more complex checks (e.g., uniqueness of email/RADA ID if necessary)

    if (empty($errors)) {
        // Prepare the SQL UPDATE statement
        $updateQuery = "
            UPDATE farmers SET
                firstName = ?,
                lastName = ?,
                email = ?,
                phoneNumber = ?,
                radaIdNumber = ?,
                address1 = ?,
                address2 = ?,
                parish = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = mysqli_prepare($conn, $updateQuery);
        
        // Bind parameters: (8 strings + 1 integer for ID)
        mysqli_stmt_bind_param($stmt, "ssssssssi", 
            $firstName, 
            $lastName, 
            $email, 
            $phoneNumber, 
            $radaIdNumber, 
            $address1, 
            $address2, 
            $parish, 
            $farmerId
        );

        if (mysqli_stmt_execute($stmt)) {
            $successMessage = "Farmer profile updated successfully! Redirecting...";
            
            // Re-fetch the updated data to refresh the form (or redirect)
            // Redirect after a slight delay for better UX
            header("Refresh: 2; URL=viewFarmer.php?id=$farmerId");
            
        } else {
            $errors['db'] = "Database update failed: " . mysqli_error($conn);
        }

        mysqli_stmt_close($stmt);
        
        // If there were DB errors, keep the POSTed data in the form fields
        if (!isset($errors['db'])) {
             // If successful, update the $farmer array so the form shows new data immediately
            $farmer = [
                'farmerId' => $farmerId,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phoneNumber' => $phoneNumber,
                'radaIdNumber' => $radaIdNumber,
                'address1' => $address1,
                'address2' => $address2,
                'parish' => $parish
            ];
        }
    }
    
    // If validation failed, the form variables will still hold the POSTed (bad) data for the user to correct.
    // If successful, we already redirected.
}

// Data to pre-fill the form: use POST data if submission failed, otherwise use fetched $farmer data
$formData = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors) ? $_POST : $farmer;

// Define available parishes (Replace with a DB fetch or config array if needed)
$parishes = [
    'Clarendon', 'Hanover', 'Kingston', 'Manchester', 'Portland', 'St. Andrew', 
    'St. Ann', 'St. Catherine', 'St. Elizabeth', 'St. James', 'St. Mary', 
    'St. Thomas', 'Trelawny', 'Westmoreland'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Farmer: <?= htmlspecialchars($farmer['firstName'] . ' ' . $farmer['lastName']); ?> | StockCrop Admin</title>
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
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-12">
            <h2 class="fw-bold mb-0">
                <a href="viewFarmer.php?id=<?= $farmerId; ?>" class="text-secondary text-decoration-none me-2">&leftarrow;</a>
                Edit Profile: <?= htmlspecialchars($farmer['firstName'] . ' ' . $farmer['lastName']); ?>
            </h2>
            <p class="text-muted">Farmer ID: #<?= $farmerId ?> | User ID: #<?= $farmer['userId'] ?></p>
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
        <form method="POST" action="editFarmer.php?id=<?= $farmerId; ?>">
            <h4 class="mb-4 text-secondary">Personal Details</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" class="form-control <?= isset($errors['firstName']) ? 'is-invalid' : '' ?>" id="firstName" name="firstName" value="<?= htmlspecialchars($formData['firstName'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['firstName'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" class="form-control <?= isset($errors['lastName']) ? 'is-invalid' : '' ?>" id="lastName" name="lastName" value="<?= htmlspecialchars($formData['lastName'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['lastName'] ?? '' ?></div>
                </div>
            </div>

            <h4 class="mb-4 text-secondary">Contact & ID</h4>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['email'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="phoneNumber" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control <?= isset($errors['phoneNumber']) ? 'is-invalid' : '' ?>" id="phoneNumber" name="phoneNumber" value="<?= htmlspecialchars($formData['phoneNumber'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['phoneNumber'] ?? '' ?></div>
                </div>
                <div class="col-md-6">
                    <label for="radaIdNumber" class="form-label">RADA ID Number</label>
                    <input type="text" class="form-control <?= isset($errors['radaIdNumber']) ? 'is-invalid' : '' ?>" id="radaIdNumber" name="radaIdNumber" value="<?= htmlspecialchars($formData['radaIdNumber'] ?? ''); ?>" required>
                    <div class="invalid-feedback"><?= $errors['radaIdNumber'] ?? '' ?></div>
                </div>
            </div>

            <h4 class="mb-4 text-secondary">Location Details</h4>
            <div class="row g-3 mb-5">
                <div class="col-md-4">
                    <label for="parish" class="form-label">Parish</label>
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

            <div class="d-flex justify-content-between pt-3 border-top">
                <a href="viewFarmer.php?id=<?= $farmerId; ?>" class="btn btn-secondary">
                    <span class="material-symbols-outlined align-middle me-1">cancel</span>
                    Cancel / Go Back
                </a>
                <button type="submit" class="btn btn-success btn-lg">
                    <span class="material-symbols-outlined align-middle me-1">save</span>
                    Save Changes
                </button>
            </div>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>