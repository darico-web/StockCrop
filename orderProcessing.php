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

// --- 1. Handle Batch Status Update Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_update_action'])) {
    $selectedOrders = $_POST['selected_orders'] ?? [];
    $newStatus = $_POST['new_batch_status'] ?? '';
    
    if (!empty($selectedOrders) && in_array($newStatus, ['Ready for Pickup', 'Shipped', 'Delivered', 'Cancelled'])) {
        // Sanitize IDs for SQL
        $inClause = implode(',', array_map('intval', $selectedOrders));
        
        $updateQuery = "UPDATE orders SET status = ? WHERE id IN ($inClause)";
        $stmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($stmt, "s", $newStatus);
        
        if (mysqli_stmt_execute($stmt)) {
            $updateMessage = count($selectedOrders) . " order(s) successfully updated to " . htmlspecialchars($newStatus) . ".";
        

            if (!empty($selectedOrders) && in_array($newStatus, ['Ready for Pickup', 'Shipped', 'Delivered', 'Cancelled'])) {
    // Sanitize IDs for SQL
    $inClause = implode(',', array_map('intval', $selectedOrders));
    
    $updateQuery = "UPDATE orders SET status = ? WHERE id IN ($inClause)";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "s", $newStatus);
    
    if (mysqli_stmt_execute($stmt)) {
        $updateMessage = count($selectedOrders) . " order(s) successfully updated to " . htmlspecialchars($newStatus) . ".";

        // --- NOTIFICATION LOGIC START ---
        // Fetch unique customer IDs for the updated orders
        $custQuery = "SELECT DISTINCT customerId, id FROM orders WHERE id IN ($inClause)";
        $resultCust = mysqli_query($conn, $custQuery);
        if ($resultCust) {
            while ($rowCust = mysqli_fetch_assoc($resultCust)) {
                $custId = $rowCust['customerId'];
                $orderId = $rowCust['id'];
                $message = "Your order #$orderId status has been updated to '$newStatus'.";
                $stmtNotif = mysqli_prepare($conn, "INSERT INTO notifications (userId, type, message) VALUES (?, 'order', ?)");
                mysqli_stmt_bind_param($stmtNotif, "is", $custId, $message);
                mysqli_stmt_execute($stmtNotif);
                mysqli_stmt_close($stmtNotif);
            }
        }
        // --- NOTIFICATION LOGIC END ---
        
    } else {
        $updateError = "Database error during batch update.";
    }
    mysqli_stmt_close($stmt);
}

        } else {
            $updateError = "Database error during batch update.";
        }
    } elseif (!empty($selectedOrders)) {
         $updateError = "Invalid status selected for batch update.";
    }
}


// --- 2. Handle Filtering and Search ---
$filterStatus = trim($_GET['status'] ?? 'actionable'); // Default to 'actionable'
$search = trim($_GET['search'] ?? '');
$searchQuery = "%" . $search . "%"; 

$whereClause = "1"; // Default WHERE clause
$params = "";
$paramValues = [];

// Apply status filter 
if ($filterStatus === 'actionable') {
    // Actionable orders are those that need attention from Admin or Farmer
    $whereClause = "o.status IN ('Processing', 'Pending', 'Ready for Pickup')";
} elseif ($filterStatus !== 'all') {
    // Filter by a specific status
    $whereClause = "o.status = ?";
    $params .= "s";
    $paramValues[] = ucfirst($filterStatus);
}

// Apply search filter
if (!empty($search)) {
    // Add filtering for Order ID, Customer Name, or Customer Email
    $whereClause .= " AND (
        o.id = ? OR 
        c.firstName LIKE ? OR 
        c.lastName LIKE ? OR 
        u.email LIKE ?
    )";
    $params .= "isss";
    $paramValues[] = $search; 
    $paramValues[] = $searchQuery;
    $paramValues[] = $searchQuery;
    $paramValues[] = $searchQuery;
}

// --- 3. Fetch Orders ---
$query = "
    SELECT 
        o.id AS orderId,
        o.orderDate,
        o.totalAmount,
        o.deliveryMethod,
        o.status,
        c.firstName,
        c.lastName,
        u.email,
        u.id AS customerUserId
    FROM orders o
    JOIN customers c ON o.customerId = c.id
    JOIN users u ON c.userId = u.id
    WHERE {$whereClause}
    ORDER BY o.orderDate DESC
";

$stmt = mysqli_prepare($conn, $query);

// Bind parameters dynamically (using the fixed reference logic)
if (!empty($paramValues)) {
    $bindArray = array_merge([$stmt, $params], $paramValues);
    $bindReferences = [];
    foreach ($bindArray as $key => $value) {
        if ($key > 1) { 
            $bindReferences[] = &$bindArray[$key];
        } else {
            $bindReferences[] = $value;
        }
    }
    if (!call_user_func_array('mysqli_stmt_bind_param', $bindReferences)) {
        error_log("Parameter binding failed: " . mysqli_stmt_error($stmt));
    }
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    error_log("Order query failed: " . mysqli_error($conn));
    $orderData = [];
} else {
    $orderData = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
mysqli_stmt_close($stmt);

$orderCount = count($orderData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Processing | StockCrop Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/png" href="assets/icon.png">
    <style>
        :root { --primary-green: #2f8f3f; --sidebar-width: 250px; }
        body { background: #f8faf8; display: flex; }
        .content { margin-left: var(--sidebar-width); padding: 30px; width: calc(100% - var(--sidebar-width)); min-height: 100vh; }
        .filter-section { background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<?php include 'adminSidePanel.php'; ?>

<div class="content mt-5">

    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold mb-0">Order Fulfillment Dashboard 🚚</h2>
            <p class="text-muted">Orders requiring administrative action: <?= $orderCount ?> results</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#batchActionModal">
                <span class="material-symbols-outlined align-middle me-1">batch_prediction</span>
                Batch Status Update
            </button>
        </div>
    </div>
    
    <?php if (isset($updateMessage)): ?>
        <div class="alert alert-success"><span class="material-symbols-outlined align-middle me-1">check_circle</span> <?= $updateMessage ?></div>
    <?php endif; ?>
    <?php if (isset($updateError)): ?>
        <div class="alert alert-danger"><span class="material-symbols-outlined align-middle me-1">error</span> <?= $updateError ?></div>
    <?php endif; ?>

    <div class="filter-section mb-4">
        <form method="GET" action="orderProcessing.php" class="row g-3 align-items-center">
            
            <div class="col-md-4">
                <label for="statusFilter" class="form-label visually-hidden">Filter by Status</label>
                <select class="form-select" id="statusFilter" name="status">
                    <option value="actionable" <?= $filterStatus == 'actionable' ? 'selected' : ''; ?>>Actionable (Pending, Processing, Ready)</option>
                    <option value="ready for pickup" <?= $filterStatus == 'ready for pickup' ? 'selected' : ''; ?>>Ready for Pickup (Dispatch)</option>
                    <option value="shipped" <?= $filterStatus == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?= $filterStatus == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?= $filterStatus == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="all" <?= $filterStatus == 'all' ? 'selected' : ''; ?>>All Orders</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="search" class="form-label visually-hidden">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search Order #, Customer Name or Email..." value="<?= htmlspecialchars($search); ?>">
            </div>
            
            <div class="col-md-2 text-end">
                <button type="submit" class="btn btn-success w-100">
                    <span class="material-symbols-outlined align-middle">filter_alt</span> Filter
                </button>
            </div>
        </form>
    </div>
    
    <div class="order-card p-0">
        <form id="orderTableForm" method="POST" action="orderProcessing.php">
        <input type="hidden" name="batch_update_action" value="1">
        
        <?php if ($orderCount > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"><input type="checkbox" id="selectAll"></th>
                            <th scope="col">Order #</th>
                            <th scope="col">Date</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Delivery</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderData as $order): 
                            $customerName = htmlspecialchars($order['firstName'] . ' ' . $order['lastName']);
                            $status = strtolower($order['status']);
                            
                            $statusBadge = match($status) {
                                'delivered', 'completed' => '<span class="badge bg-success">Delivered</span>',
                                'shipped', 'out for delivery' => '<span class="badge bg-info">Shipped</span>',
                                'ready for pickup' => '<span class="badge bg-secondary">Ready for Pickup</span>',
                                'processing' => '<span class="badge bg-primary">Processing</span>',
                                'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
                                'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
                                default => '<span class="badge bg-secondary">Unknown</span>',
                            };
                        ?>
                            <tr>
                                <td><input type="checkbox" name="selected_orders[]" value="<?= $order['orderId']; ?>" class="order-checkbox"></td>
                                <th scope="row"><?= $order['orderId']; ?></th>
                                <td><?= date("M j, H:i", strtotime($order['orderDate'])); ?></td>
                                <td><a href="viewCustomer.php?id=<?= $order['customerUserId']; ?>" class="text-decoration-none"><?= $customerName ?></a></td>
                                <td>$<?= number_format($order['totalAmount'], 2); ?></td>
                                <td><?= htmlspecialchars($order['deliveryMethod']); ?></td>
                                <td><?= $statusBadge; ?></td>
                                <td>
                                    <a href="viewOrder.php?id=<?= $order['orderId']; ?>" class="btn btn-sm btn-outline-info me-2" title="View Order Details"><span class="material-symbols-outlined align-middle" style="font-size: 18px;">visibility</span></a>
                                    <a href="generateInvoice.php?id=<?= $order['orderId']; ?>" class="btn btn-sm btn-outline-success" target="_blank" title="Print/Send Invoice"><span class="material-symbols-outlined align-middle" style="font-size: 18px;">print</span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-3 border-top d-flex justify-content-between align-items-center">
                <span id="selectedCount" class="text-muted">0 orders selected.</span>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center m-4 p-4">
                <span class="material-symbols-outlined text-info" style="font-size: 48px;">info</span>
                <h4 class="mt-3">No Orders Found</h4>
                <p>No orders match the current status filter (<?= htmlspecialchars(ucfirst($filterStatus)); ?>) and search criteria.</p>
            </div>
        <?php endif; ?>
        </form>
    </div>
    
</div>

<div class="modal fade" id="batchActionModal" tabindex="-1" aria-labelledby="batchActionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="batchActionModalLabel">Batch Status Update</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="batchUpdateForm" method="POST" action="orderProcessing.php">
      <input type="hidden" name="batch_update_action" value="1">
      <div class="modal-body">
        <p>You are about to change the status for <strong id="modalSelectedCount">0</strong> selected orders.</p>
        <div class="mb-3">
            <label for="new_batch_status" class="form-label">Select New Status:</label>
            <select class="form-select" id="new_batch_status" name="new_batch_status" required>
                <option value="">Choose...</option>
                <option value="Ready for Pickup">Ready for Pickup (Packages consolidated)</option>
                <option value="Shipped">Shipped (Handed to 3PL)</option>
                <option value="Delivered">Delivered</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>
        <p class="text-danger small">Warning: This action will update ALL selected orders immediately.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-info" id="confirmBatchUpdate">Confirm Update</button>
      </div>
      </form>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.order-checkbox');
        const selectedCountSpan = document.getElementById('selectedCount');
        const modalSelectedCountStrong = document.getElementById('modalSelectedCount');
        const batchUpdateForm = document.getElementById('batchUpdateForm');
        
        function updateCount() {
            const checkedCount = document.querySelectorAll('.order-checkbox:checked').length;
            selectedCountSpan.textContent = `${checkedCount} order(s) selected.`;
            modalSelectedCountStrong.textContent = checkedCount;
            // Optionally disable batch buttons if count is 0
            document.querySelector('[data-bs-target="#batchActionModal"]').disabled = checkedCount === 0;
        }

        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateCount();
        });

        checkboxes.forEach(cb => cb.addEventListener('change', updateCount));
        
        // Initial count update
        updateCount(); 

        // Important: When the modal form submits, transfer the selected checkboxes' IDs
        batchUpdateForm.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert("Please select at least one order to update.");
                e.preventDefault();
                return;
            }
            
            // Replicate the selected checkboxes into the modal form
            checkedBoxes.forEach(cb => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_orders[]';
                hiddenInput.value = cb.value;
                batchUpdateForm.appendChild(hiddenInput);
            });
            // The modal will close upon successful submission
        });
    });
</script>
</body>
</html>