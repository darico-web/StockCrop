<?php
session_start();
include 'config.php';

// --- 1. AUTHENTICATION AND CART CHECK ---
$is_logged_in = isset($_SESSION['id']);
$has_session_cart = isset($_SESSION['cart']) && !empty($_SESSION['cart']);
$user_id = $_SESSION['id'] ?? null;

if (!$is_logged_in && !$has_session_cart) {
    header("Location: shop.php");
    exit();
}

// --- 2. HANDLE FORM SUBMISSION ---
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // 2.1. Input sanitization
    $fullName = mysqli_real_escape_string($conn, $_POST['fullName'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? 'Customer Pickup'); 
    $paymentMethod = mysqli_real_escape_string($conn, $_POST['paymentMethod'] ?? '');
    $deliveryMethod = mysqli_real_escape_string($conn, $_POST['deliveryMethod'] ?? '');
    $shippingFee = floatval($_POST['shippingFee'] ?? 0.00);

    if (empty($fullName) || empty($phone) || empty($paymentMethod) || empty($deliveryMethod)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Missing contact or payment details.'];
        header("Location: checkout.php");
        exit();
    }

    if ($deliveryMethod === 'Delivery' && $address === 'Customer Pickup') {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'A delivery address is required for doorstep delivery.'];
        header("Location: checkout.php");
        exit();
    }

    // 2.2. Get customerId
    $sql_customer = "SELECT id FROM customers WHERE userId = ?";
    $stmt_customer = mysqli_prepare($conn, $sql_customer);
    mysqli_stmt_bind_param($stmt_customer, "i", $user_id);
    mysqli_stmt_execute($stmt_customer);
    $result_customer = mysqli_stmt_get_result($stmt_customer);
    $customer_row = mysqli_fetch_assoc($result_customer);
    mysqli_stmt_close($stmt_customer);

    if (!$customer_row) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Customer profile not found. Please re-login.'];
        header("Location: checkout.php");
        exit();
    }
    $customerId = $customer_row['id'];

    // 2.3. Fetch cart items
    $cart_items = [];
    $order_subtotal = 0.0;
    $order_status = 'Pending'; 

    $sql = "SELECT ci.id AS cartItemId, ci.productId, ci.quantity, ci.price, 
                   p.stockQuantity, p.farmerId, p.productName, p.imagePath
            FROM cartItems ci
            JOIN cart c ON ci.cartId = c.id
            JOIN products p ON ci.productId = p.id
            WHERE c.userId = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Your cart is empty.'];
        header("Location: shop.php");
        exit();
    }

    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['quantity'] > $row['stockQuantity']) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Stock quantity exceeded for a product. Please review your cart.'];
            header("Location: cart.php"); 
            exit();
        }

        $line_total = $row['quantity'] * $row['price'];
        $order_subtotal += $line_total;
        $row['lineTotal'] = $line_total;
        $cart_items[] = $row;
    }
    mysqli_stmt_close($stmt);

    $order_total = $order_subtotal + $shippingFee;

    // 2.4. Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Insert order
        $sql_order = "INSERT INTO orders (customerId, orderDate, totalAmount, shippingFee, deliveryMethod, deliveryAddress, recipientPhone, paymentMethod, status) 
                      VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
        $stmt_order = mysqli_prepare($conn, $sql_order);
        mysqli_stmt_bind_param($stmt_order, "idddssss", 
            $customerId, 
            $order_total, 
            $shippingFee, 
            $deliveryMethod, 
            $address, 
            $phone, 
            $paymentMethod, 
            $order_status
        );
        mysqli_stmt_execute($stmt_order);
        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_order);

        // Insert order_items and update stock
        $sql_item = "INSERT INTO order_items (orderId, productId, farmerId, quantity, priceAtPurchase, lineTotal) VALUES (?, ?, ?, ?, ?, ?)";
        $sql_stock = "UPDATE products SET stockQuantity = stockQuantity - ? WHERE id = ?";

        foreach ($cart_items as $item) {
            $stmt_item = mysqli_prepare($conn, $sql_item);
            mysqli_stmt_bind_param($stmt_item, "iiiidd", 
                $order_id, 
                $item['productId'], 
                $item['farmerId'], 
                $item['quantity'], 
                $item['price'], 
                $item['lineTotal']
            );
            mysqli_stmt_execute($stmt_item);
            mysqli_stmt_close($stmt_item);

            $stmt_stock = mysqli_prepare($conn, $sql_stock);
            mysqli_stmt_bind_param($stmt_stock, "ii", $item['quantity'], $item['productId']);
            mysqli_stmt_execute($stmt_stock);
            mysqli_stmt_close($stmt_stock);
        }

        // Clear user's cartItems
        $stmt_cart = mysqli_prepare($conn, "SELECT id FROM cart WHERE userId = ?");
        mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
        mysqli_stmt_execute($stmt_cart);
        $result_cart = mysqli_stmt_get_result($stmt_cart);
        $cart_row = mysqli_fetch_assoc($result_cart);
        mysqli_stmt_close($stmt_cart);

        if ($cart_row) {
            $cart_id = $cart_row['id'];
            $stmt_clear = mysqli_prepare($conn, "DELETE FROM cartItems WHERE cartId = ?");
            mysqli_stmt_bind_param($stmt_clear, "i", $cart_id);
            mysqli_stmt_execute($stmt_clear);
            mysqli_stmt_close($stmt_clear);
        }

        mysqli_commit($conn);

        $_SESSION['message'] = ['type' => 'success', 'text' => "Order #{$order_id} placed successfully!"];
        header("Location: orderConfirmation.php?orderId=" . $order_id);
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Order Placement Failed: " . $e->getMessage());
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Order failed. Please try again.'];
        header("Location: checkout.php");
        exit();
    }
}

// --- 3. Display Form Logic (GET request) ---
$customer_address = [
    'fullName' => '',
    'phone' => '',
    'address' => ''
];

if ($user_id) {
    $stmt = mysqli_prepare(
        $conn, 
        "SELECT firstName, lastName, phoneNumber, address1, address2, parish 
         FROM customers 
         WHERE userId = ?"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $customer_address['fullName'] = trim($row['firstName'] . ' ' . $row['lastName']);
            $customer_address['phone'] = $row['phoneNumber'];
            $customer_address['address'] = implode(', ', array_filter([$row['address1'], $row['address2'], $row['parish']]));

            $_SESSION['firstName'] = $row['firstName'];
            $_SESSION['lastName'] = $row['lastName'];
            $_SESSION['phone'] = $row['phoneNumber'];
            $_SESSION['address1'] = $row['address1'];
            $_SESSION['address2'] = $row['address2'];
            $_SESSION['parish'] = $row['parish'];
        } else {
            $customer_address['fullName'] = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
            $customer_address['phone'] = $_SESSION['phone'] ?? '';
            $customer_address['address'] = implode(', ', array_filter([$_SESSION['address1'] ?? '', $_SESSION['address2'] ?? '', $_SESSION['parish'] ?? '']));
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>StockCrop | Checkout</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> 
    <link rel="icon" type="image/png" href="assets/icon.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        .product-list-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php';?> 
    <div class="container py-5 mb-4">
        <h1 class="display-6 mb-5 fw-bold">Secure Checkout</h1>

        <div class="row">
            
            <div class="col-lg-7">
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light fw-bold">1. Authentication</div>
                    <div class="card-body">
                        <?php if ($is_logged_in): ?>
                            <div class="alert alert-success">
                                <span class="material-symbols-outlined align-middle me-2">check_circle</span>
                                You are logged in as <?= htmlspecialchars($_SESSION['firstName']) ?>.
                                <a href="logout.php" class="alert-link">Log out?</a>
                            </div>
                        <?php else: ?>
                            <p class="mb-3">
                                Please Log In to proceed with your order, or Register if you are a new customer.
                            </p>
                            
                            <a href="login.php?redirect=checkout.php" class="btn btn-primary me-2">Log In to Continue</a>
                            <a href="registerCustomer.php?redirect=<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-primary">Register to Continue</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_logged_in): ?>
                    <form id="shippingForm" action="checkout.php" method="POST"> 

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light fw-bold">2. Delivery Options</div>
                            <div class="card-body">
                                <p class="fw-semibold mb-3">How would you like to receive your order?</p>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="deliveryMethod" id="methodDelivery" value="Delivery" required onchange="updateDeliveryOption(500.00)">
                                    <label class="form-check-label fw-bold" for="methodDelivery">
                                        Doorstep Delivery (Flat Rate)
                                    </label>
                                    <small class="d-block text-muted ms-4">
                                        StockCrop Logistics Partner delivers to your address. Cost: J$500.00
                                    </small>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="deliveryMethod" id="methodPickup" value="Pickup" required onchange="updateDeliveryOption(0.00)" checked>
                                    <label class="form-check-label fw-bold" for="methodPickup">
                                        Customer Pickup
                                    </label>
                                    <small class="d-block text-muted ms-4">
                                        Collect from the designated StockCrop Sorting Hub. Cost: Free
                                    </small>
                                </div>
                                
                                <input type="hidden" name="shippingFee" id="shippingFeeInput" value="0.00">
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light fw-bold">3. Contact and Location Details</div>
                            <div class="card-body">
                                <div id="addressFields">
                                    <div class="alert alert-info py-2" id="pickupMessage" style="display:block;">
                                        <span class="material-symbols-outlined align-middle me-2">storefront</span>
                                        You will receive the Pickup Location and Time Slot upon order confirmation.
                                    </div>

                                    <div class="mb-3">
                                        <label for="fullName" class="form-label">Recipient Full Name</label>
                                        <input type="text" class="form-control" name="fullName" id="fullName" 
                                               value="<?= $customer_address['fullName'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Contact Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" id="phone"
                                               value="<?= $customer_address['phone'] ?>" required>
                                    </div>
                                    <div class="mb-3" id="deliveryAddressContainer" style="display:none;">
                                        <label for="address" class="form-label">Delivery Address</label>
                                        <input type="text" class="form-control" name="address" id="address" 
                                               value="<?= $customer_address['address'] ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light fw-bold">4. Payment Method</div>
                            <div class="card-body">
                                <p class="fw-semibold">Select Payment Method:</p>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="paymentCOD" value="COD" required onchange="togglePlaceOrderButton()">
                                    <label class="form-check-label" for="paymentCOD">
                                        Cash on Delivery / Cash on Pickup
                                    </label>
                                    <small class="text-muted d-block ms-4">Pay in cash when your order is delivered or picked up.</small>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="paymentOnline" value="Online" required disabled>
                                    <label class="form-check-label text-muted" for="paymentOnline">
                                        Credit/Debit Card (Coming Soon)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-3 fw-bold fs-5" id="placeOrderBtn" disabled>
                            <span class="material-symbols-outlined align-middle me-1">lock</span>
                            Place Order
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning shadow-sm">
                        Please log in or register above to complete your order.
                    </div>
                <?php endif; ?>
                </div>

            <div class="col-lg-5">
                <div class="card shadow-sm sticky-top" style="top: 80px;">
                    <div class="card-header bg-success text-white fw-bold">Order Summary</div>
                    <div class="card-body">
                        
                        <ul id="checkoutItemList" class="list-group list-group-flush mb-4">
                            <li class="list-group-item text-center text-muted">Loading items...</li>
                        </ul>

                        <div class="d-flex justify-content-between">
                            <span>Subtotal (<span id="itemCount">0</span> items)</span>
                            <span id="cartSubtotal" class="fw-bold">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between my-2">
                            <span>Shipping</span>
                            <span id="shippingCost" class="text-secondary">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-3 fw-bold fs-4">
                            <span>Order Total</span>
                            <span id="cartTotal">$0.00</span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Global variable to hold the chosen shipping fee
    let currentShippingFee = 0.00;
    
    document.addEventListener('DOMContentLoaded', () => {
        fetchCartSummary();
        // Initialize the delivery option to Pickup (0.00 fee) on load
        // NOTE: The radio button 'methodPickup' is checked by default in HTML
        updateDeliveryOption(0.00); 
    });

    /**
     * Reuses the AJAX call to get cart data for the final summary.
     */
    function fetchCartSummary() {
        const itemList = document.getElementById('checkoutItemList');
        
        fetch('getCartItems.php')
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                renderSummary(data.items);
            })
            .catch(err => {
                itemList.innerHTML = '<li class="list-group-item text-danger">Error loading cart.</li>';
                console.error("Fetch error:", err);
            });
    }

    /**
     * Updates the global shipping fee, toggles address fields, and triggers recalculation.
     * @param {number} fee - The shipping fee (e.g., 500.00 for Delivery, 0.00 for Pickup).
     */
    function updateDeliveryOption(fee) {
        currentShippingFee = fee;
        document.getElementById('shippingFeeInput').value = fee.toFixed(2);
        
        const deliveryAddressContainer = document.getElementById('deliveryAddressContainer');
        const pickupMessage = document.getElementById('pickupMessage');
        const addressInput = document.getElementById('address');
        
        // Delivery Logic
        if (fee > 0) {
            // Delivery selected - Show address and make it required
            deliveryAddressContainer.style.display = 'block';
            addressInput.setAttribute('required', 'required');
            pickupMessage.style.display = 'none';
        } else {
            // Pickup selected - Hide address, remove requirement
            deliveryAddressContainer.style.display = 'none';
            addressInput.removeAttribute('required');
            //addressInput.value = 'Customer Pickup'; // Default value for form submission (important!)
            pickupMessage.style.display = 'block';
        }

        renderSummary(null, true); // Force recalculation with new fee
        togglePlaceOrderButton(); 
    }

    /**
     * Renders the product list and calculates the summary totals.
     * @param {Array} items - The cart items array (optional if only updating totals).
     * @param {boolean} forceRecalculate - Flag to force using currentShippingFee.
     */
    function renderSummary(items, forceRecalculate = false) {
        const itemList = document.getElementById('checkoutItemList');
        let subtotal = 0;
        let totalItems = 0;
        
        const shippingCost = currentShippingFee; 
        
        // If items are provided, render the list
        if (items) {
            itemList.innerHTML = ''; 

            if (items.length === 0) {
                itemList.innerHTML = '<li class="list-group-item text-center text-muted">Your cart is empty!</li>';
                return;
            }

            items.forEach(item => {
                subtotal += item.lineTotal;
                totalItems += item.quantity;
                
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item d-flex justify-content-between align-items-center product-list-item';
                listItem.innerHTML = `
                    <div class="d-flex align-items-center">
                        <img src="${item.imagePath}" alt="${item.productName}" class="me-2">
                        <div>
                            <small class="mb-0 fw-bold">${item.productName}</small>
                            <small class="d-block text-muted">Qty: ${item.quantity} x $${item.price.toFixed(2)}</small>
                        </div>
                    </div>
                    <span class="fw-bold">$${item.lineTotal.toFixed(2)}</span>
                `;
                itemList.appendChild(listItem);
            });
            // Store subtotal and item count on the elements themselves for easier recalculation
            itemList.dataset.subtotal = subtotal;
            itemList.dataset.totalItems = totalItems;
        } else if (forceRecalculate) {
            // Use stored values if not refreshing the item list
            subtotal = parseFloat(itemList.dataset.subtotal || 0);
            totalItems = parseInt(itemList.dataset.totalItems || 0);
        } else {
            return; // Exit if no items provided and no recalculation needed
        }

        const orderTotal = subtotal + shippingCost;

        // Update Summary Totals
        document.getElementById('itemCount').textContent = totalItems;
        document.getElementById('cartSubtotal').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('shippingCost').textContent = `$${shippingCost.toFixed(2)}`;
        document.getElementById('cartTotal').textContent = `$${orderTotal.toFixed(2)}`;
    }
    
    /**
     * Enables the "Place Order" button only when delivery option and payment method are selected, 
     * and the form fields are valid.
     */
    function togglePlaceOrderButton() {
        const deliverySelected = document.querySelector('input[name="deliveryMethod"]:checked');
        const paymentSelected = document.querySelector('input[name="paymentMethod"]:checked');
        
        // Trigger HTML5 validation check
        const formValid = document.getElementById('shippingForm').checkValidity();
        const btn = document.getElementById('placeOrderBtn');

        if (deliverySelected && paymentSelected && formValid) {
            btn.removeAttribute('disabled');
        } else {
            btn.setAttribute('disabled', 'disabled');
        }
    }
    
    // Attach the validation check to the form's input changes
    document.getElementById('shippingForm')?.addEventListener('input', togglePlaceOrderButton);
    </script>
</body>
</html>