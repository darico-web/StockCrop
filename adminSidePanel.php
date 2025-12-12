<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    include_once 'config.php';

    // Only allow Admins (roleId = 1)
    if (!isset($_SESSION['id']) || $_SESSION['roleId'] != 1) {
        header("Location: index.php");
        exit;
    }

    // Fetch admin info
    if (!isset($admin_info)) {
        $userId = $_SESSION['id'];
        $stmt = mysqli_prepare($conn, "SELECT email FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin_info = ($result && mysqli_num_rows($result) > 0) ?
            mysqli_fetch_assoc($result) :
            ['email' => 'Admin'];
    }
?>
<style>
    :root {
        --sc-primary-green: #028037;
        --sc-dark-green: #01632c;
        --sc-hover-green: #146c43;
        --sc-background: #f5f7fa;
        --sc-light-text: #e0e0e0;
    }

    body { 
        font-family: 'Roboto', sans-serif; 
        background-color: var(--sc-background); 
        margin:0; 
        overflow-x: hidden;
    }

    /* --- Sidebar --- */
    .sidebar {
        width: 250px; 
        background-color: var(--sc-primary-green);
        color: #fff;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        padding: 1rem 0;
        position: fixed;
        top: 0;
        bottom: 0;
        z-index: 1030;
        box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    }

    .sidebar .logo-container {
        padding: 10px 20px 30px;
        text-align: center;
    }

    .sidebar .logo-container img { max-height: 40px; }

    .sidebar-link {
        color: var(--sc-light-text);
        text-decoration: none;
        padding: 12px 25px;
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 0 10px;
        border-radius: 8px;
        transition: background-color 0.3s, color 0.3s;
    }

    .sidebar-link i { font-size: 1.2rem; }

    .sidebar-link:hover, .sidebar-link.active { 
        background-color: var(--sc-dark-green); 
        color: #fff; 
    }

    .sidebar-link.active {
        font-weight: 700;
        border-left: 5px solid #ffc107;
        padding-left: 20px;
    }

    /* --- Top Navbar --- */
    .navbar-top {
        background-color: #fff;
        border-bottom: 1px solid #ddd;
        color: #333;
        z-index: 1020;
        position: fixed;
        left: 250px; 
        right: 0;
        height: 60px; 
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 1.5rem;
    }

    .navbar-top .navbar-brand { 
        color: var(--sc-primary-green); 
        font-weight: 700; 
        font-size: 1.2rem; 
    }

    .navbar-top .btn-logout { 
        background: #ffc107; 
        color: #212529; 
        font-weight: 600; 
        border: none;
        border-radius: 6px;
        padding: 6px 15px;
    }

    /* --- Responsive Layout (Mobile) --- */
    @media(max-width: 992px){
        .sidebar { 
            position: fixed; 
            left: -250px;
            transition: left 0.3s;
        }
        .show-sidebar { left: 0; }
        .navbar-top { left: 0; }
    }
</style>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">

<!-- 🔝 Top Navbar -->
<nav class="navbar navbar-top">
    <button class="navbar-toggler d-lg-none me-3" type="button" onclick="toggleSidebar()">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="fw-bold">
        Welcome, <?php echo htmlspecialchars($admin_info['email']); ?>
    </div>
    <a href="logout.php" class="btn btn-logout">Logout</a>
</nav>

<!-- Admin Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo-container">
        <a href="adminDashboard.php">
            <img src="assets/logo2.png" alt="StockCrop Logo">
        </a>
    </div>

    <a href="adminDashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'adminDashboard.php' ? 'active' : ''; ?>">
        <span class="material-icons-outlined">home</span> Home
    </a>

    <a href="farmManagement.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'farmManagement.php' ? 'active' : ''; ?>">
        <span class="material-icons-outlined">group</span> Farm Management
    </a>

    <a href="productInventory.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'productInventory.php' ? 'active' : ''; ?>">
        <span class="material-icons-outlined">inventory</span> Product Inventory
    </a>

    <a href="customerAccounts.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'customerAccounts.php' ? 'active' : ''; ?>">
        <span class="material-icons-outlined">person</span> Customer Accounts
    </a>

    <a href="orderProcessing.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'orderProcessing.php' ? 'active' : ''; ?>">
        <span class="material-icons-outlined">receipt_long</span> Order Processing
    </a>

    <a href="reports.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
        <span class="material-icons-outlined">bar_chart</span> Reports & Stats
    </a>

    <a href="settings.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
        <span class="material-icons-outlined">settings</span> Settings
    </a>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('show-sidebar');
    }
</script>
