<?php
// NOTE: ASSUMES session_start() and include 'config.php' run BEFORE this file is included.

// --- 1. Cart Count Logic ---
$cart_count = 0;

if (isset($_SESSION['id'])) {
    // LOGGED-IN: Calculate total quantity from the database
    // --- FIX APPLIED HERE: Correctly JOINING 'cart' and 'cartItems' ---
    $userId = $_SESSION['id'];
    $stmt = mysqli_prepare(
        $conn, 
        "SELECT SUM(ci.quantity) AS total 
         FROM cartItems ci
         JOIN cart c ON ci.cartId = c.id
         WHERE c.userId = ?"
    );
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        // Use ?? 0 for safety, and cast to int
        $cart_count = intval($row['total'] ?? 0); 
        
        mysqli_stmt_close($stmt);
    }
} elseif (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    // GUEST: Calculate total count from the session array (values are quantities)
    $cart_count = array_sum($_SESSION['cart']); 
}


// --- 2. User Info Logic (for conditional display) ---
$is_logged_in = isset($_SESSION['id']);
$user_name = $_SESSION['firstName'] ?? 'User'; 
$user_role_id = $_SESSION['roleId'] ?? null;

// Get the current page name for the Log In button's redirect parameter
$current_page = basename($_SERVER['PHP_SELF']);

?>

<nav class="navbar navbar-expand-lg bg-white shadow-sm py-2 sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="assets/logo.png" alt="StockCrop Logo" height="45" class="me-2">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" 
                aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-between align-items-center" id="navbarNavDropdown">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 d-flex align-items-center gap-lg-3 text-center">
                <li class="nav-item"><a href="index.php" class="nav-link fw-semibold">Home</a></li>
                <li class="nav-item"><a href="shop.php" class="nav-link fw-semibold">Marketplace</a></li>
                <li class="nav-item"><a href="about.php" class="nav-link fw-semibold">About Us</a></li>
                <li class="nav-item"><a href="contact.php" class="nav-link fw-semibold">Contact Us</a></li>
            </ul>

            <form action="shop.php" method="GET" class="d-flex align-items-center bg-light rounded-pill px-3 py-1 mb-2 mb-lg-0 search-bar">
                <span class="material-symbols-outlined text-secondary me-2">search</span>
                <input type="text" name="search" class="form-control border-0 bg-light" 
                        placeholder="Search for products..." 
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </form>

            <div class="d-flex align-items-center ms-lg-3 gap-2 mt-2 mt-lg-0">
                
                <a href="#" 
                data-bs-toggle="offcanvas" 
                data-bs-target="#cartOffcanvas"
                aria-controls="cartOffcanvas"
                class="d-flex justify-content-center align-items-center position-relative flex-shrink-0 text-decoration-none"
                style="background-color: #FFEB3B; width: 42px; height: 42px; border-radius: 50%;">
                    <span class="material-symbols-outlined text-dark" style="font-size: 24px;">shopping_cart</span>
                    <?php if ($cart_count > 0): ?>
                        <span id="cart-item-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $cart_count ?>
                        </span>
                    <?php endif; ?>
                </a>

<?php if ($is_logged_in && $user_role_id == 2): // Only for farmers ?>
    <?php
    // Get farmer ID from session
    $farmerId = $_SESSION['farmerId'] ?? null;
    $newOrders = [];
    if ($farmerId) {
        $stmt = mysqli_prepare($conn, "
            SELECT o.id AS orderId, c.firstName, c.lastName
            FROM order_items oi
            JOIN orders o ON oi.orderId = o.id
            JOIN customers c ON o.customerId = c.id
            WHERE oi.farmerId = ? AND oi.status = 'Pending'
            ORDER BY o.orderDate DESC
            LIMIT 5
        ");
        mysqli_stmt_bind_param($stmt, "i", $farmerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $newOrders = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
    $newOrdersCount = count($newOrders);
    ?>

    <div class="dropdown ms-2">
        <button class="btn btn-outline-secondary position-relative" type="button" id="notificationsDropdown" 
                data-bs-toggle="dropdown" aria-expanded="false" style="width:42px; height:42px; border-radius:50%;">
            <span class="material-symbols-outlined">notifications</span>
            <?php if($newOrdersCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $newOrdersCount ?>
                    <span class="visually-hidden">unread notifications</span>
                </span>
            <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="min-width:250px;">
            <?php if(empty($newOrders)): ?>
                <li class="dropdown-item text-muted">No new orders</li>
            <?php else: ?>
                <?php foreach($newOrders as $order): ?>
                    <li class="dropdown-item">
                        Order #<?= $order['orderId']; ?> from <?= htmlspecialchars($order['firstName'].' '.$order['lastName']); ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>
                

                <?php if ($is_logged_in): ?>
                    <div class="dropdown">
                        <button style="background-color: #E57373;" class="btn d-flex align-items-center text-white fw-semibold px-3 py-2 rounded-pill dropdown-toggle" 
                                type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="material-symbols-outlined me-1">person</span><?= htmlspecialchars($user_name) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <?php if ($user_role_id == 2): ?>
                                <li><a class="dropdown-item" href="farmerDashboard.php">Dashboard</a></li>
                            <?php elseif ($user_role_id == 3): ?>
                                <li><a class="dropdown-item" href="customerDashboard.php">My Dashboard</a></li>
                                <li><a class="dropdown-item" href="customerDashboard.php#profile">My Profile</a></li>
                                <li><a class="dropdown-item" href="customerDashboard.php#orders">My Orders</a></li>
                                <li><a class="dropdown-item" href="customerDashboard.php#wishlist">My Wishlist</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php">Log Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="dropdown">
                        <button class="btn btn-signup d-flex align-items-center fw-semibold px-3 py-2 rounded-pill dropdown-toggle" 
                                type="button" id="signupDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="material-symbols-outlined me-1">person</span>Sign Up
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="signupDropdown">
                            <li><a class="dropdown-item" href="registerFarmer.php">Register as a Farmer</a></li>
                            <li><a class="dropdown-item" href="registerCustomer.php">Register as a Customer</a></li>
                        </ul>
                    </div>
                    <a href="login.php?redirect=<?php echo htmlspecialchars($current_page); ?>" class="btn btn-login fw-semibold px-3 py-2 rounded-pill">
                        Log In
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
    <div class="offcanvas-header bg-success text-white">
        <h5 class="offcanvas-title fw-bold" id="cartOffcanvasLabel">
            <span class="material-symbols-outlined me-2 align-middle">shopping_cart</span>
            Your Cart
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    
    <div class="offcanvas-body d-flex flex-column p-0">
        <div id="cartPanelItems" class="flex-grow-1 overflow-auto">
            <div class="text-center text-muted p-5">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading items...</p>
            </div>
        </div>
        
        <div id="cartPanelFooter" class="p-3 border-top shadow-lg bg-white sticky-bottom">
            </div>
    </div>
</div>

<script>
// === GLOBAL JAVASCRIPT FUNCTIONS FOR CART PANEL CONTROLS ===

/**
 * Updates the cart count badge in the navbar.
 */
function updateNavbarCartCount(newCount) {
    const cartLink = document.querySelector('a[data-bs-target="#cartOffcanvas"]');
    let badge = document.getElementById('cart-item-count');

    if (newCount > 0) {
        if (!badge) {
            // Create badge if it doesn't exist
            badge = document.createElement('span');
            badge.id = 'cart-item-count';
            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
            cartLink.appendChild(badge);
        }
        badge.textContent = newCount;
    } else {
        // Remove badge if count is 0
        if (badge) {
            badge.remove();
        }
    }
}

/**
 * Function called by the +/- buttons inside the cart panel (modal).
 * Sends AJAX request to updateCartQuantity.php and then refreshes the panel.
 */
function updateCartPanelQuantity(productId, newQty, maxQty = 99) {
    if (newQty > maxQty) {
        alert("Cannot add more than " + maxQty + " items (available stock).");
        return;
    }
    
    if (newQty < 1) {
        if (!confirm("Quantity is 0. Do you want to remove this item from the cart?")) {
            return;
        }
        newQty = 0; // Signal removal to server
    }

    fetch('updateCartQuantity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `productId=${productId}&quantity=${newQty}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            updateNavbarCartCount(data.newCount); // Update the badge
            fetchCartPanelContent(); // Refresh the modal content
        } else {
            alert("Error updating quantity: " + data.message);
        }
    })
    .catch(err => {
        console.error("Network error while updating quantity.", err);
        alert("An error occurred. Please try again.");
    });
}


/**
 * Fetches the content for the Offcanvas panel.
 * This function is now set to run when the modal is shown.
 */
function fetchCartPanelContent() {
    const itemsContainer = document.getElementById('cartPanelItems');
    const footerContainer = document.getElementById('cartPanelFooter');
    
    // Show loading state
    if(itemsContainer) itemsContainer.innerHTML = `
        <div class="text-center text-muted p-5">
            <div class="spinner-border text-success" role="status"></div>
            <p class="mt-2">Loading items...</p>
        </div>`;
    if(footerContainer) footerContainer.innerHTML = ''; 

    fetch('getCartPanelContent.php')
        .then(res => res.json()) // IMPORTANT: Expecting JSON response
        .then(data => {
            if (itemsContainer) itemsContainer.innerHTML = data.itemsHtml;
            if (footerContainer) footerContainer.innerHTML = data.footerHtml;
        })
        .catch(err => {
            if (itemsContainer) itemsContainer.innerHTML = '<div class="alert alert-danger m-3">Failed to load cart.</div>';
            console.error("Error loading cart panel content:", err);
        });
}

// Attach the fetch function to the offcanvas 'show' event
document.addEventListener('DOMContentLoaded', () => {
    const offcanvasElement = document.getElementById('cartOffcanvas');
    if (offcanvasElement) {
        // Call fetchCartPanelContent() every time the offcanvas is opened
        offcanvasElement.addEventListener('show.bs.offcanvas', fetchCartPanelContent);
    }
});
</script>

<style>
/* --- ANIMATION FROM ORIGINAL CODE --- */
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
.cart-bounce {
    animation: bounce 0.4s ease;
}

/* --- BUTTON STYLES RESTORED FROM YOUR PROVIDED CSS --- */

/* Primary/User Dropdown (Green: #028037) - Assuming this is your logged-in button style */
.btn-primary {
    background-color: #028037 !important; 
    color: white !important;
    border-color: #028037 !important;
    font-weight: 600;
    transition: 0.3s;
}

.btn-primary:hover {
    background-color: #016f30 !important;
    border-color: #016f30 !important;
}

/* Sign Up Button (RED: #E57373) - Restoring your specific .btn-signup style */
.btn-signup {
    background-color: #E57373 !important;
    color: white !important;
    border-color: #E57373 !important;
    font-weight: 600;
    transition: background-color 0.3s ease;
}

/* Hover + dropdown open */
.btn-signup:hover,
.dropdown.show .btn-signup {
    background-color: #C62828 !important;
    border-color: #C62828 !important;
    color: white !important;
}

/* Log In Button (ORANGE: #F4A261) - Restoring your specific .btn-login style */
.btn-login {
    background-color: #F4A261 !important;
    color: white !important;
    border-color: #F4A261 !important;
    font-weight: 600;
    transition: background-color 0.3s ease;
}

.btn-login:hover {
    background-color: #E76F51 !important;
    border-color: #E76F51 !important;
    color: white !important;
}
</style>
