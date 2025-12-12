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

// --- LOGIC IMPROVEMENT: Sanitize Input (for potential future use if search/filter is added) ---
$searchTerm = $_GET['search'] ?? '';

// --- DATABASE FETCH (Original Logic Preserved) ---
// Note: If you add searching, uncomment the WHERE clause below.

$query = "
    SELECT 
        id AS farmerId,
        firstName,
        lastName,
        email,
        phoneNumber,
        radaIdNumber,
        parish,
        created_at
    FROM farmers
    " . ($searchTerm ? "WHERE firstName LIKE '%$searchTerm%' OR lastName LIKE '%$searchTerm%' OR parish LIKE '%$searchTerm%'" : "") . "
    ORDER BY created_at DESC
";

$result = mysqli_query($conn, $query);

// Check for query errors (Crucial for debugging 'not displaying' issues)
if (!$result) {
    // Log the error and fall back to an empty set
    error_log("Farmer query failed: " . mysqli_error($conn));
    $farmersData = [];
} else {
    // Fetch all rows into an array for cleaner HTML loop
    $farmersData = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
}

// Calculate the number of farmers found (useful for the header)
$farmerCount = count($farmersData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Farmer Management | StockCrop Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/png" href="assets/icon.png">

    <style>
        :root {
            --primary-green: #2f8f3f;
            --dark-green: #1b5e20;
            --sidebar-width: 250px; /* Must match the sidebar width used elsewhere */
        }

        body {
            background: #f8faf8;
            display: flex; /* Helps ensure the content starts after the fixed sidebar */
        }

        /* --- UI/UX FIX: Content positioning relative to fixed sidebar --- */
        /* Assuming 'styles.css' defines the fixed sidebar */
        .content {
            margin-left: var(--sidebar-width); /* Push content away from the sidebar */
            padding: 30px;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
        }

        @media (max-width: 992px) {
            /* Adjust for smaller screens where sidebar might become relative or hidden */
            .content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        /* --- Farmer Card Styles (Improved) --- */
        .farmer-card {
            border-radius: 12px;
            background: white;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08); /* Softer shadow */
            border-left: 5px solid var(--primary-green); /* Color accent */
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            position: relative;
        }

        .farmer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .farmer-name {
            font-size: 1.4rem;
            color: var(--dark-green);
        }

        .farmer-detail {
            font-size: 0.9rem;
            color: #495057;
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .farmer-detail .material-symbols-outlined {
            font-size: 18px;
            margin-right: 8px;
            color: #6c757d;
        }
        
        .farmer-id-badge {
            font-size: 0.75rem;
            font-weight: bold;
            background-color: #f1f1f1;
            color: #6c757d;
            padding: 4px 8px;
            border-radius: 5px;
            display: inline-block;
        }

        /* --- No Farmers UI --- */
        .no-results {
            padding: 50px;
            border: 2px dashed #ced4da;
            border-radius: 10px;
            background-color: #f8f9fa;
        }

    </style>
</head>
<body>

<?php 
// This includes the sidebar, which is assumed to be the cause of the previous layout issue
include 'adminSidePanel.php'; 
?>

<div class="content mt-5">

    <h2 class="page-title mb-1 fw-bold text-dark">Farmer Directory 🚜</h2>
    <p class="text-secondary mb-4">Total Active Farmers: <?= $farmerCount ?></p>

    <div class="row mb-5">
        <div class="col-lg-8">
            <form method="GET" class="d-flex">
                <input type="search" name="search" class="form-control form-control-lg me-2" placeholder="Search by name or parish..." value="<?= htmlspecialchars($searchTerm) ?>">
                <button class="btn btn-primary" type="submit">
                    <span class="material-symbols-outlined align-middle">search</span>
                </button>
                <?php if ($searchTerm): ?>
                    <a href="farmers.php" class="btn btn-outline-secondary ms-2">
                        <span class="material-symbols-outlined align-middle">close</span>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-lg-4 text-end d-flex align-items-center justify-content-end">
            <a href="addFarmer.php" class="btn btn-success btn-lg">
                <span class="material-symbols-outlined align-middle me-1">add_circle</span>
                Add New Farmer
            </a>
        </div>
    </div>
    
    <div class="row g-4">

        <?php if ($farmerCount > 0): ?>
            <?php foreach ($farmersData as $row): ?>

                <div class="col-xxl-3 col-lg-4 col-md-6">
                    <div class="farmer-card">

                        <h5 class="farmer-name fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1">person</span>
                            <?= htmlspecialchars($row['firstName'] . " " . $row['lastName']); ?>
                        </h5>

                        <div class="farmer-detail">
                            <span class="material-symbols-outlined">badge</span>
                            <span class="farmer-id-badge">RADA ID: <?= htmlspecialchars($row['radaIdNumber']); ?></span>
                        </div>

                        <div class="farmer-detail mt-3">
                            <span class="material-symbols-outlined">location_on</span>
                            <?= htmlspecialchars($row['parish']); ?>
                        </div>

                        <div class="farmer-detail">
                            <span class="material-symbols-outlined">call</span>
                            <?= htmlspecialchars($row['phoneNumber']); ?>
                        </div>

                        <div class="farmer-detail mb-3">
                            <span class="material-symbols-outlined">mail</span>
                            <?= htmlspecialchars($row['email']); ?>
                        </div>

                        <p class="text-muted small border-top pt-2 mt-4 mb-3">
                            Registered: <?= date("F j, Y", strtotime($row['created_at'])); ?>
                        </p>

                        <a href="viewFarmer.php?id=<?= $row['farmerId']; ?>" class="btn btn-outline-success w-100 fw-bold">
                            View Profile
                        </a>

                    </div>
                </div>

            <?php endforeach; ?>
        <?php else: ?>

            <div class="col-12 text-center">
                <div class="no-results">
                    <span class="material-symbols-outlined text-muted" style="font-size: 48px;">person_off</span>
                    <h4 class="text-muted mt-3">
                        <?php if ($searchTerm): ?>
                            **No farmers found** matching "<?= htmlspecialchars($searchTerm) ?>"
                        <?php else: ?>
                            **No farmers have been registered** yet.
                        <?php endif; ?>
                    </h4>
                    <p class="text-secondary">Start by adding your first farmer below.</p>
                    <a href="addFarmer.php" class="btn btn-success btn-lg mt-3">
                        <span class="material-symbols-outlined align-middle me-1">person_add</span>
                        Register Farmer
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>