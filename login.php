<?php
session_start();

// NOTE: Ensure 'config.php' establishes the $conn variable (mysqli connection)
include 'config.php';
// NOTE: Ensure 'session.php' includes the redirectIfLoggedIn() function
include 'session.php'; 

// --- Redirection Logic ---
// Get the redirect URL from the query string (e.g., login.php?redirect=checkout.php).
// If no redirect is specified, default to 'customerHome.php'.
$redirect_url = htmlspecialchars($_GET['redirect'] ?? 'customerDashboard.php');

// If the user is already logged in, redirect them away from the login page.
redirectIfLoggedIn();

// Initialize error message to avoid undefined variable warning
$errorMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit"])) {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // 1. Query to find user by email using a prepared statement (security)
    $query = mysqli_prepare($conn, "SELECT id, roleId, password_hash FROM users WHERE email = ?");
    mysqli_stmt_bind_param($query, "s", $email);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        // 2. Verify password against the stored hash
        if (password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true); // Prevent session fixation

            // Set core session variables
            $_SESSION["id"] = $user["id"];
            $_SESSION["roleId"] = $user["roleId"];


         // 3. Redirect based on role

         if ($user["roleId"] == 2) {

            // Get farmer data using userId
            $farmer_query = mysqli_prepare($conn, 
                "SELECT farmerId, businessName FROM farmers WHERE userId = ?");
            mysqli_stmt_bind_param($farmer_query, "i", $user["id"]);
            mysqli_stmt_execute($farmer_query);
            $farmer_result = mysqli_stmt_get_result($farmer_query);
        
            if ($farmer_row = mysqli_fetch_assoc($farmer_result)) {
                $_SESSION['farmerId'] = $farmer_row['farmerId'];
                $_SESSION['businessName'] = $farmer_row['businessName'];
            }
        
            mysqli_stmt_close($farmer_query);
        
            header("Location: farmerDashboard.php");
            exit();
        }
            } elseif ($user["roleId"] == 1) {
                // Admin login always goes to the dashboard
                header("Location: adminDashboard.php");
                exit();
            } elseif ($user["roleId"] == 3) { 
                // CUSTOMER LOGIN FLOW

                // Fetch Customer data (firstName) for the session
                $customer_query = mysqli_prepare($conn, "SELECT firstName, lastName, address1, address2, parish FROM customers WHERE userId = ?");
                mysqli_stmt_bind_param($customer_query, "i", $user["id"]);
                mysqli_stmt_execute($customer_query);
                $customer_result = mysqli_stmt_get_result($customer_query);
                
                if ($customer_row = mysqli_fetch_assoc($customer_result)) {
                    $_SESSION['firstName'] = $customer_row['firstName'];
                    $_SESSION['lastName']  = $customer_row['lastName'];
                    $_SESSION['address1']  = $customer_row['address1'];
                    $_SESSION['address2']  = $customer_row['address2'];
                    $_SESSION['parish']  = $customer_row['parish'];
                }
                mysqli_stmt_close($customer_query);

                // Redirect to the originally requested page ($redirect_url)
                header("Location: " . $redirect_url);
                exit();

            } else {
                $errorMessage = "Invalid account role. Please contact support.";
            }
        } else {
            $errorMessage = "Invalid login credentials. Please try again.";
        }
    } else {
        $errorMessage = "Invalid email or password.";
    }
    // Clean up statement for the primary query
    mysqli_stmt_close($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StockCrop | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #F9FAFB;
        }

        /* Minimal navbar for login page */
        nav.navbar {
            background-color: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        nav.navbar .navbar-brand img {
            height: 45px;
        }

        .login-hero {
            background: url('https://images.unsplash.com/photo-1601004890684-d8cbf643f5f2?auto=format&fit=crop&w=1400&q=80') no-repeat center center;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .login-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(2, 128, 55, 0.65);
        }

        .login-card {
            position: relative;
            z-index: 2;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }

        .login-card h3 {
            color: #028037;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .login-card p {
            color: #555;
            margin-bottom: 30px;
        }

        .btn-login {
            background-color: #FFEB3B;
            color: #212529;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-login:hover {
            background-color: #FDD835;
        }

        .login-card a {
            color: #028037;
            text-decoration: none;
        }

        .login-card a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .login-hero {
                padding: 40px 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg py-2 sticky-top">
        <div class="container d-flex align-items-center">
            <div class="d-flex align-items-center me-auto">
                <a class="navbar-brand me-4" href="index.php">
                    <img src="assets/logo.png" alt="StockCrop Logo" height="45">
                </a>
                <a href="index.php" class="nav-link fw-semibold ms-5">Home</a>
            </div>
        </div>
    </nav>

    <section class="login-hero">
        <div class="login-overlay"></div>
        <div class="login-card">
            <h3 class="text-center">Welcome Back!</h3>
            <p class="text-center">Sign in to access your StockCrop account</p>

            <?php if ($errorMessage != ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>

            <form action="login.php?redirect=<?= urlencode($redirect_url) ?>" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required placeholder="you@example.com">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required placeholder="Enter your password">
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" name="submit" class="btn btn-login">Login</button>
                </div>

                <p class="text-center mb-0">
                    Don't have an account? <br>
                    <a href="registerFarmer.php">Register as a Farmer</a> | 
                    <a href="registerCustomer.php?redirect=<?= urlencode($redirect_url) ?>">Register as a Customer</a>
                </p>
            </form>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
