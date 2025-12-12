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

// 1. Input Validation and ID Check
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: farmers.php");
    exit();
}

$farmerId = (int)$_GET['id'];
$farmer = null;

// 2. Fetch Farmer Details (Updated to use address1 and address2)
$stmt = mysqli_prepare($conn, "
    SELECT 
        id AS farmerId,
        userId,
        firstName,
        lastName,
        email,
        phoneNumber,
        radaIdNumber,
        address1,
        address2,
        parish,
        created_at
    FROM farmers
    WHERE id = ?
");
mysqli_stmt_bind_param($stmt, "i", $farmerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $farmer = $row;
} else {
    // Farmer not found
    header("Location: farmers.php");
    exit();
}
mysqli_stmt_close($stmt);

// Combine address lines for cleaner display
$fullAddress = trim(
    (empty($farmer['address1']) ? '' : $farmer['address1']) . 
    (empty($farmer['address2']) ? '' : ', ' . $farmer['address2'])
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($farmer['firstName'] . ' ' . $farmer['lastName']); ?> | Profile</title>
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

        /* Profile Card Styling */
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .profile-header {
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-group {
            padding: 10px 0;
            border-bottom: 1px dashed #f0f0f0;
        }

        .info-group:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .info-value {
            font-weight: bold;
            color: #343a40;
            margin-top: 5px;
        }
        
        .info-label .material-symbols-outlined {
            font-size: 18px;
            margin-right: 8px;
            color: var(--primary-green);
        }

        .badge-rada {
            background-color: var(--primary-green);
            color: white;
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0">
                <a href="farmManagement.php" class="text-secondary text-decoration-none me-2">&leftarrow;</a>
                Farmer Profile
            </h2>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <span class="material-symbols-outlined align-middle me-1">delete</span>
                Delete Farmer
            </button>
            <a href="editFarmer.php?id=<?= $farmer['farmerId']; ?>" class="btn btn-warning">
                <span class="material-symbols-outlined align-middle me-1">edit</span>
                Edit Profile
            </a>
        </div>
    </div>

    <div class="profile-card mb-5">
        <div class="profile-header">
            <h3 class="fw-bold text-dark">
                <?= htmlspecialchars($farmer['firstName'] . ' ' . $farmer['lastName']); ?>
            </h3>
            <span class="badge badge-rada">
                RADA ID: <?= htmlspecialchars($farmer['radaIdNumber']); ?>
            </span>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h5 class="text-secondary mb-3">Contact Information</h5>
                <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">call</span> Phone Number</div>
                    <div class="info-value"><?= htmlspecialchars($farmer['phoneNumber']); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">mail</span> Email Address</div>
                    <div class="info-value"><?= htmlspecialchars($farmer['email']); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">location_city</span> Parish</div>
                    <div class="info-value"><?= htmlspecialchars($farmer['parish']); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">home</span> Full Address</div>
                    <div class="info-value"><?= htmlspecialchars($fullAddress) ?: 'N/A'; ?></div>
                </div>
            </div>

            <div class="col-md-6">
                <h5 class="text-secondary mb-3">Administrative Data</h5>
                 <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">person_outline</span> User Account ID</div>
                    <div class="info-value"><?= htmlspecialchars($farmer['userId']); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">person_pin</span> Internal Farmer ID</div>
                    <div class="info-value"><?= $farmer['farmerId']; ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">date_range</span> Account Created</div>
                    <div class="info-value"><?= date("F j, Y, g:i a", strtotime($farmer['created_at'])); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label"><span class="material-symbols-outlined">verified_user</span> Status</div>
                    <div class="info-value text-success">Active</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="p-4 bg-white rounded-3 shadow-sm">
                <h4 class="text-dark fw-bold mb-3">Recent Activity and Orders</h4>
                <p class="text-muted">
                    This section will eventually display related data such as their active orders, produce listings, and transaction history.
                </p>
                <button class="btn btn-outline-primary btn-sm">View Orders</button>
                <button class="btn btn-outline-info btn-sm ms-2">View Produce</button>
            </div>
        </div>
    </div>
    
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to permanently delete <?= htmlspecialchars($farmer['firstName'] . ' ' . $farmer['lastName']); ?>? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="deleteFarmer.php?id=<?= $farmer['farmerId']; ?>" class="btn btn-danger">Delete Permanently</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>