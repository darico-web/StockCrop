<?php
    // Start session if it hasn't been started already
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Function to check if a user is logged in
    function isLoggedIn() {
        return isset($_SESSION['id']);
    }

    // Redirects if logged in
    function redirectIfLoggedIn() {
    if (isset($_SESSION['roleId'])) {
        if ($_SESSION['roleId'] == 2) {
            header("Location: farmerDashboard.php");
        } elseif ($_SESSION['roleId'] == 3) {
            header("Location: customerDashboard.php");
        } elseif ($_SESSION['roleId'] == 1) {
            header("Location: adminDashboard.php");
        } else {
            header("Location: login.php");
        }
        exit();
    }
}


    // Redirect to login page if not logged in
    function redirectIfNotLoggedIn() {
        if (!isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
?>
