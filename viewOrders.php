<?php
include 'session.php';
include 'config.php';

// Sanity checks
redirectIfNotLoggedIn();
if ($_SESSION['roleId'] != 2) {
    header("Location: index.php");
    exit;
}

$farmerId = $_SESSION['id'];

// --- SORTING LOGIC ---
$sortParam = $_GET['sort'] ?? 'date_desc'; // Default sort
$orderBy = '';
$sortLabel = 'Newest First'; // Default label

switch ($sortParam) {
    case 'date_asc':
        $orderBy = 'o.orderDate ASC, o.id ASC';
        $sortLabel = 'Oldest First';
        break;
    case 'total_desc':
        $orderBy = 'o.totalAmount DESC, o.orderDate DESC';
        $sortLabel = 'Highest Total';
        break;
    case 'total_asc':
        $orderBy = 'o.totalAmount ASC, o.orderDate DESC';
        $sortLabel = 'Lowest Total';
        break;
    case 'date_desc':
    default:
        $orderBy = 'o.orderDate DESC, o.id DESC';
        $sortLabel = 'Newest First';
        break;
}

// --- DATE RANGE FILTERING LOGIC ---
$dateFilter = '';
$dateRangeParam = $_GET['date_range'] ?? 'all';
$stmtParams = [$farmerId];
$stmtTypes = 'i';
$dateFilterLabel = 'All Orders';
// Flag to trigger auto-print after load
$shouldPrint = isset($_GET['print']) && $_GET['print'] === 'true'; 

switch ($dateRangeParam) {
    case 'today':
        $dateFilter = "AND DATE(o.orderDate) = CURDATE()";
        $dateFilterLabel = "Today's Orders";
        break;
    case 'week':
        // Orders placed in the last 7 days (including today)
        $dateFilter = "AND o.orderDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $dateFilterLabel = "Last 7 Days";
        break;
    case 'custom':
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        if ($startDate && $endDate) {
            // Apply the filter and adjust parameters for the prepared statement
            $dateFilter = "AND DATE(o.orderDate) BETWEEN ? AND ?";
            $stmtParams[] = $startDate;
            $stmtParams[] = $endDate;
            $stmtTypes .= 'ss';
            $dateFilterLabel = "Custom Range: {$startDate} to {$endDate}";
        } else {
            // Revert to 'all' if custom range parameters are missing
            $dateRangeParam = 'all';
        }
        break;
    case 'all':
    default:
        // No date filtering, using default label
        break;
}


// Fetch farmer's orders with essential details, including total and address
$sql = "
    SELECT 
        o.id AS orderId,
        o.orderDate,
        o.totalAmount,           /* Kept for sorting purposes */
        o.deliveryMethod,
        o.deliveryAddress,       /* Delivery location */
        o.status AS orderStatus,
        c.firstName AS customerFirstName,
        c.lastName AS customerLastName,
        c.phoneNumber AS customerPhone,
        oi.id AS itemId,
        oi.productId,
        oi.quantity,
        oi.lineTotal,            /* <-- Crucial for calculating farmer's subtotal */
        oi.status AS itemStatus, 
        p.productName
    FROM order_items oi
    JOIN orders o ON oi.orderId = o.id
    JOIN products p ON oi.productId = p.id
    JOIN customers c ON o.customerId = c.id
    JOIN farmers f ON oi.farmerId = f.id
    WHERE f.userId = ?
    {$dateFilter} /* <-- Date filter insertion */
    ORDER BY {$orderBy}
";


$stmt = mysqli_prepare($conn, $sql);

// Dynamically bind parameters using array unpacking
if (count($stmtParams) > 1) {
    mysqli_stmt_bind_param($stmt, $stmtTypes, ...$stmtParams);
} else {
    // Only farmerId is bound
    mysqli_stmt_bind_param($stmt, $stmtTypes, $stmtParams[0]);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Organize orders by orderId
$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orderId = $row['orderId'];

    // Initialize order info and subtotal if not set
    if (!isset($orders[$orderId]['orderInfo'])) {
        $orders[$orderId]['orderInfo'] = [
            'orderDate' => $row['orderDate'],
            'totalAmount' => $row['totalAmount'], 
            'deliveryMethod' => htmlspecialchars($row['deliveryMethod']),
            'deliveryAddress' => htmlspecialchars($row['deliveryAddress'] ?? 'N/A'),
            'orderStatus' => htmlspecialchars($row['orderStatus']),
            'customerName' => htmlspecialchars($row['customerFirstName'] . ' ' . $row['customerLastName']),
            'customerPhone' => htmlspecialchars($row['customerPhone']),
            'farmerSubtotal' => 0.00, // Initialize farmer's subtotal
        ];
    }
    
    // Group order items and accumulate line total for the farmer's subtotal
    $orders[$orderId]['items'][] = [
        'itemId' => $row['itemId'],
        'productId' => $row['productId'],
        'productName' => htmlspecialchars($row['productName']),
        'quantity' => $row['quantity'],
        'lineTotal' => $row['lineTotal'],
        'status' => htmlspecialchars($row['itemStatus']) 
    ];
    
    // Accumulate the line total for the farmer's subtotal
    $orders[$orderId]['orderInfo']['farmerSubtotal'] += $row['lineTotal'];
}
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders | Farmer Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <style>
        :root {
            --sc-primary-green: #028037;
            --sc-dark-green: #01632c;
            --sc-hover-green: #146c43;
            --sc-background: #f5f7fa;
            --sc-light-text: #e0e0e0;
        }
        body { font-family: 'Roboto', sans-serif; background-color: var(--sc-background); margin:0; overflow-x: hidden; }

        /* --- Sidebar --- */
        .sidebar { width: 250px; background-color: var(--sc-primary-green); color: #fff; flex-shrink: 0; display: flex; flex-direction: column; padding: 1rem 0; position: fixed; top: 0; bottom: 0; z-index: 1030; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar .logo-container { padding: 10px 20px 30px; text-align: center; }
        .sidebar .logo-container img { max-height: 40px; }
        .sidebar-link { color: var(--sc-light-text); text-decoration: none; padding: 12px 25px; display: flex; align-items: center; gap: 15px; margin: 0 10px; border-radius: 8px; transition: background-color 0.3s, color 0.3s; }
        .sidebar-link i { font-size: 1.2rem; }
        .sidebar-link:hover, .sidebar-link.active { background-color: var(--sc-dark-green); color: #fff; }
        .sidebar-link.active { font-weight: 700; border-left: 5px solid #ffc107; padding-left: 20px; }

        /* --- Top Navbar --- */
        .navbar-top { background-color: #fff; border-bottom: 1px solid #ddd; color: #333; z-index: 1020; position: fixed; left: 250px; right: 0; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 1.5rem; }
        .navbar-top .navbar-brand { color: var(--sc-primary-green); font-weight: 700; font-size: 1.2rem; }
        .navbar-top .btn-logout { background: #ffc107; color: #212529; font-weight: 600; border: none; border-radius: 6px; padding: 6px 15px; }

        /* --- Main content --- */
        .content { margin-left: 250px; padding: 20px 30px; padding-top: 80px; min-height: 100vh; }

        /* Custom Styles for Orders - Enhanced for UX */
        .card-order { 
            border-radius: 12px; 
            border: none; 
            box-shadow: 0 6px 16px rgba(0,0,0,0.08); /* Stronger Shadow */
            transition: transform 0.2s, box-shadow 0.3s;
            /* No cursor: pointer if they are expandable, to encourage clicking */
        }
        .card-order:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .card-order .card-header { 
            background-color: var(--sc-dark-green) !important; 
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem 1.5rem;
            display: flex; /* Ensure flex for icon */
            cursor: pointer; /* Restore cursor on the header */
        }
        
        .order-details-summary {
            background-color: #f8f9fa; /* Lighter background for details */
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px; /* Increased padding */
            margin-bottom: 1rem;
        }
        .order-details-summary strong { color: var(--sc-primary-green); }

        /* Status Badges - Refined Colors */
        .status-badge {
            padding: 0.5em 0.8em;
            font-size: 0.8em;
            border-radius: 0.5rem;
            font-weight: 600;
            min-width: 90px;
            text-align: center;
        }
        /* Item Statuses */
        .status-Pending { background-color: #ffc107; color: #333; }
        .status-Processed { background-color: #0d6efd; color: #fff; }
        .status-Shipped { background-color: #17a2b8; color: #fff; }
        /* Overall Order Statuses - for the pill in the header */
        .status-Overall-Pending { background-color: #fd7e14; color: #fff; } /* Orange */
        .status-Overall-Processed { background-color: #0d6efd; color: #fff; } /* Primary */
        .status-Overall-Shipped { background-color: #17a2b8; color: #fff; } /* Info */
        .status-Overall-Delivered { background-color: #28a745; color: #fff; } /* Success */


        .update-form {
            position: relative;
        }
        .loading-spinner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            z-index: 10;
        }
        
        /* Collapse Icon Rotation */
        .toggle-icon {
            transition: transform 0.3s;
        }
        .card-header[aria-expanded="false"] .toggle-icon {
            transform: rotate(-90deg);
        }

        /* Filter/Sort Bar Layout */
        .filter-sort-bar {
            /* Ensures alignment and spacing for combined bar */
        }
        .filter-bar .btn {
            font-weight: 500;
            min-width: 100px;
        }
        .filter-bar .btn.active {
            background-color: var(--sc-primary-green) !important;
            color: white !important;
            border-color: var(--sc-primary-green) !important;
        }
        
        /* Dropdown Width Fix */
        .dropdown-menu {
            min-width: 300px; /* Ensure enough width for longer text options like "Print This Week's Orders (Last 7 Days)" */
        }

        /* Responsive Tweaks */
        @media(max-width: 992px){
            .sidebar { position: fixed; left: -250px; transition: left 0.3s; }
            .content { margin-left: 0; padding-top: 70px; }
            .navbar-top { left: 0; height: 70px; padding: 0.5rem 1rem; }
            .show-sidebar { left: 0; }
        }
        /* Mobile Specific: Stacking controls and table adjustments */
        @media (max-width: 768px) {
            /* Filter and Sort bar stacking */
            .filter-sort-bar {
                flex-direction: column;
                align-items: flex-start !important;
            }
            .filter-bar {
                width: 100%;
                justify-content: space-between;
                margin-bottom: 1rem !important;
            }
            .filter-bar h5 { display: none !important; }

            /* Order Details stacking */
            .order-details-summary .col-md-6 {
                margin-bottom: 1rem !important; /* Ensure separation when stacked */
            }
            .order-details-summary .col-md-6:last-child {
                margin-bottom: 0 !important;
            }

            /* Item Table responsiveness */
            .table-responsive-sm {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            /* Reduce padding on table headers/data for dense display */
            .table > :not(caption) > * > * {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            /* Hide line total column on mobile for brevity */
            .table thead tr th:nth-child(3),
            .table tbody tr td:nth-child(3) {
                display: none;
            }
        }

        /* Styles for Printing (Media Query) */
        @media print {
            body { 
                background-color: #fff !important; 
                margin: 0; 
                padding: 0;
            }
            .sidebar, .navbar-top, .filter-sort-bar, .update-form, #message-container, .d-none-print, .dropdown {
                display: none !important; /* Hide non-essential print items */
            }
            .content { 
                margin-left: 0 !important; 
                padding: 0 !important; 
                padding-top: 0 !important;
            }
            .card-order {
                box-shadow: none !important;
                border: 1px solid #ccc;
                page-break-after: always;
            }
            .card-order .card-header {
                background-color: #e9ecef !important;
                color: #000 !important;
                border: 1px solid #ccc;
            }
            /* Ensure item status badges are legible on white background */
            .status-badge.status-Pending { background-color: #ffc107; color: #333; border: 1px solid #333; }
            
            /* FIX: Force all collapsible content to be visible when printing */
            .collapse:not(.show) {
                display: block !important;
                visibility: visible !important;
                height: auto !important;
            }
        }
    </style>
</head>
<body>
<?php include 'sidePanel.php'; ?>

<div class="content">
    <h2 class="mb-4">Order Fulfillment 
        <small class="text-muted fw-normal fs-6 d-block d-md-inline-block ms-md-2">
            (Displaying: <?= $dateFilterLabel ?>)
        </small>
    </h2>

    <!-- Messages -->
    <div id="message-container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>
    </div>

    <?php if(empty($orders)): ?>
        <div class="alert alert-info">No orders found for the current filter criteria (<?= $dateFilterLabel ?>).</div>
    <?php else: ?>
        <!-- Filter and Sort Bar (Combined) -->
        <div class="d-md-flex justify-content-between align-items-center mb-4 p-3 bg-white rounded-xl shadow-sm filter-sort-bar d-none-print">
            <!-- Filter Bar -->
            <div class="d-flex flex-wrap align-items-center filter-bar mb-2 mb-md-0">
                <h5 class="m-0 me-3 text-muted fw-bold d-none d-sm-block">Filter:</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-1 filter-btn active" data-filter="All">All Orders</button>
                <button type="button" class="btn btn-sm btn-outline-warning me-2 mb-1 filter-btn" data-filter="Pending">Pending</button>
                <button type="button" class="btn btn-sm btn-outline-primary me-2 mb-1 filter-btn" data-filter="Processed">Processed</button>
                <button type="button" class="btn btn-sm btn-outline-info me-2 mb-1 filter-btn" data-filter="Shipped">Shipped</button>
                <button type="button" class="btn btn-sm btn-outline-success me-2 mb-1 filter-btn" data-filter="Delivered">Delivered</button>
            </div>
            
            <!-- Sorting Dropdown -->
            <div class="dropdown me-3">
                <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="material-icons-outlined align-middle" style="font-size: 18px;">sort</i>
                    <span class="d-none d-sm-inline ms-1">Sort:</span> 
                    <strong class="text-dark"><?= $sortLabel ?></strong>
                </button>
                <ul class="dropdown-menu dropdown-menu-end w-100">
                    <li><a class="dropdown-item <?= $sortParam == 'date_desc' ? 'active' : '' ?>" href="?sort=date_desc&date_range=<?= $dateRangeParam ?>">Newest First</a></li>
                    <li><a class="dropdown-item <?= $sortParam == 'date_asc' ? 'active' : '' ?>" href="?sort=date_asc&date_range=<?= $dateRangeParam ?>">Oldest First</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= $sortParam == 'total_desc' ? 'active' : '' ?>" href="?sort=total_desc&date_range=<?= $dateRangeParam ?>">Highest Total</a></li>
                    <li><a class="dropdown-item <?= $sortParam == 'total_asc' ? 'active' : '' ?>" href="?sort=total_asc&date_range=<?= $dateRangeParam ?>">Lowest Total</a></li>
                </ul>
            </div>

            <!-- Print Dropdown (UPDATED) -->
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="material-icons-outlined align-middle" style="font-size: 18px;">print</i>
                    <span class="ms-1">Print Orders</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end w-100">
                    <li><h6 class="dropdown-header">Filter and Print Range</h6></li>
                    <li><a class="dropdown-item" href="?sort=<?= $sortParam ?>&date_range=today&print=true">Print Today's Orders</a></li>
                    <li><a class="dropdown-item" href="?sort=<?= $sortParam ?>&date_range=week&print=true">Print This Week's Orders (Last 7 Days)</a></li>
                    <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#customRangeModal">Print Custom Range...</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?sort=<?= $sortParam ?>&date_range=all&print=true">Print All Orders</a></li>
                </ul>
            </div>
        </div>

        <!-- Order List Container -->
        <div id="order-list">
        <?php foreach($orders as $orderId => $order): ?>
            <div class="card mb-4 card-order" 
                 data-status="<?= $order['orderInfo']['orderStatus'] ?>" 
                 id="order-card-<?= $orderId ?>">

                <!-- Collapsible Header -->
                <div class="card-header text-white d-flex justify-content-between align-items-center"
                     data-bs-toggle="collapse" data-bs-target="#collapseItems-<?= $orderId ?>" 
                     aria-expanded="false" aria-controls="collapseItems-<?= $orderId ?>">
                    
                    <div>
                        <strong class="fs-5">Order #<?= $orderId ?></strong> 
                        <small class="text-white-50 ms-3 d-none d-sm-inline"><?= date('M d, Y', strtotime($order['orderInfo']['orderDate'])) ?></small>
                        <span class="ms-3 badge rounded-pill status-Overall-<?= $order['orderInfo']['orderStatus'] ?> p-2 d-none d-md-inline">
                            <?= $order['orderInfo']['orderStatus'] ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="fs-5 fw-bold me-3">Products: $<?= number_format($order['orderInfo']['farmerSubtotal'], 2) ?></span>
                        <i class="material-icons-outlined toggle-icon d-none-print">expand_more</i>
                        
                        <!-- Print Single Order Button -->
                        <button class="btn btn-sm btn-light ms-3 d-none-print" onclick="printSingleOrder(event, 'order-card-<?= $orderId ?>')" title="Print Picking List">
                            <i class="material-icons-outlined" style="font-size: 18px;">receipt</i>
                        </button>
                    </div>
                </div>
                
                <!-- Collapsible Body (data-bs-parent removed to allow multiple open) -->
                <div id="collapseItems-<?= $orderId ?>" class="collapse"> 
                    <div class="card-body p-4">
                        <!-- Customer and Delivery Information -->
                        <div class="row order-details-summary">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <h6 class="fw-bold text-success mb-1">Customer</h6>
                                <p class="mb-1">Name: <strong><?= $order['orderInfo']['customerName'] ?></strong></p>
                                <p class="mb-0">Phone: <strong><?= $order['orderInfo']['customerPhone'] ?></strong></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-success mb-1">Delivery</h6>
                                <p class="mb-1">Method: <strong><?= $order['orderInfo']['deliveryMethod'] ?></strong></p>
                                <p class="mb-0">Address: <strong><?= $order['orderInfo']['deliveryAddress'] ?></strong></p>
                            </div>
                        </div>

                        <h5 class="fw-bold text-muted mb-3">Items to Prepare</h5>

                        <div class="table-responsive-sm">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end d-none d-sm-table-cell">Line Total</th> <!-- Hidden on small screen -->
                                        <th>Fulfillment Status</th>
                                        <th style="width: 200px;" class="d-none-print">Update Item Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($order['items'] as $item): ?>
                                        <tr id="item-row-<?= $item['itemId'] ?>">
                                            <td><?= $item['productName'] ?></td>
                                            <td class="text-end"><?= $item['quantity'] ?></td>
                                            <td class="text-end fw-bold d-none d-sm-table-cell">$<?= number_format($item['lineTotal'], 2) ?></td> <!-- Hidden on small screen -->
                                            <td><span class="status-badge status-<?= $item['status'] ?>" data-item-id="<?= $item['itemId'] ?>"><?= $item['status'] ?></span></td>
                                            <td class="d-none-print">
                                                <!-- AJAX Status Update Form -->
                                                <form class="update-form d-flex gap-2" data-order-id="<?= $orderId ?>" data-item-id="<?= $item['itemId'] ?>">
                                                    <input type="hidden" name="itemId" value="<?= $item['itemId'] ?>">
                                                    <select name="status" class="form-select form-select-sm status-select">
                                                        <option value="Pending" <?= $item['status']=='Pending'?'selected':'' ?>>Pending</option>
                                                        <option value="Processed" <?= $item['status']=='Processed'?'selected':'' ?>>Processed</option>
                                                        <option value="Shipped" <?= $item['status']=='Shipped'?'selected':'' ?>>Shipped</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-info text-dark update-btn">
                                                        <i class="material-icons-outlined" style="font-size: 16px;">send</i>
                                                    </button>
                                                    <div class="loading-spinner d-none">
                                                        <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div> <!-- End order-list -->
    <?php endif; ?>
</div>

<!-- Custom Range Modal -->
<div class="modal fade" id="customRangeModal" tabindex="-1" aria-labelledby="customRangeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="customRangeModalLabel">Print Orders: Custom Date Range</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="GET" action="viewOrders.php">
        <div class="modal-body">
            <input type="hidden" name="date_range" value="custom">
            <input type="hidden" name="print" value="true"> <!-- NEW: Auto-print flag -->
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sortParam) ?>">
            <div class="mb-3">
                <label for="startDate" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="startDate" name="start_date" required>
            </div>
            <div class="mb-3">
                <label for="endDate" class="form-label">End Date</label>
                <input type="date" class="form-control" id="endDate" name="end_date" required>
            </div>
            <p class="text-muted small">
                Submitting this form will load and automatically print orders within the selected date range.
            </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark">Apply Filter & Print</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // PHP sets this based on the URL parameter
    const SHOULD_AUTOPRINT = <?= json_encode($shouldPrint) ?>;
    
    // Global variable to store original body state
    let originalBodyHtml = null;

    // Function to handle printing a single card
    function printSingleOrder(event, cardId) {
        event.stopPropagation(); 

        const card = document.getElementById(cardId);
        if (!card) return;

        // 1. Clone the card to work on a copy
        const cardClone = card.cloneNode(true);

        // 2. Force collapse sections to fully expand for printing
        cardClone.querySelectorAll('.collapse').forEach(collapseEl => {
            // Remove Bootstrap's collapse class
            collapseEl.classList.remove('collapse'); 
            // Add Bootstrap's show class (if needed)
            collapseEl.classList.add('show');       
            // Force inline CSS visibility properties
            collapseEl.style.display = 'block';
            collapseEl.style.height = 'auto';
            collapseEl.style.overflow = 'visible';
        });

        // 3. Ensure responsive tables are fully visible for printing
        cardClone.querySelectorAll('.table-responsive-sm').forEach(tblWrapper => {
            tblWrapper.style.overflow = 'visible';
        });
        
        // *** CRITICAL PRINT FIX V2: Apply a unique print class to all potential clipping containers ***
        // Target the main card clone and all its internal block children
        cardClone.classList.add('print-content-override');
        cardClone.querySelectorAll('div, table, tbody').forEach(el => {
            el.classList.add('print-content-override');
        });
        


        // 4. Store original body HTML
        const originalBody = document.body.innerHTML;

        // 5. Replace body with cloned card for printing, including temporary print styles
        document.body.innerHTML = `
            <style>
            /* * CRITICAL PRINT FIX V2: 
             * Override all height and overflow constraints on all block-level 
             * containers within the content being printed.
             */
            .print-content-override {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                /* Force breaks if content spans multiple print pages */
                page-break-inside: auto !important; 
            }
            /* Ensure table rows do not break across print pages */
            .print-content-override tr, .print-content-override td {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
            
            /* Hide print button/icons from the printout itself */
            .print-content-override .print-icon {
                display: none !important;
            }
            </style>
            <div style="padding: 20px; font-family: 'Roboto', sans-serif;">
                <h3 style="text-align: center; margin-bottom: 20px;">Farmer Picking List</h3>
                ${cardClone.outerHTML}
            </div>
        `;

        // 6. Delay to ensure browser fully renders the new print layout
        setTimeout(() => {
            window.print();

            // 7. Restore original body after printing
            setTimeout(() => {
                document.body.innerHTML = originalBody;
                
                // IMPORTANT: Rebind all event listeners and Bootstrap components
                if (typeof initializeDOM === 'function') {
                    initializeDOM(); 
                } else {
                    console.error("initializeDOM() function is missing. Event listeners will not be restored.");
                }
                
                // Reapply active filter state if one was set
                document.querySelector('.filter-btn.active')?.click(); 
            }, 300);
        }, 150);
    }




    // Function to re-bind all necessary DOM events
    function initializeDOM() {
        // Reinitialize collapse elements (necessary after restoring DOM)
        document.querySelectorAll('.collapse').forEach(collapseEl => {
            // Note: We use toggle: false since the parent accordion behaviour is disabled
            new bootstrap.Collapse(collapseEl, { toggle: false });
        });
        
        // Re-bind form listeners
        const forms = document.querySelectorAll('.update-form');
        forms.forEach(form => bindFormListener(form));
        
        // Re-bind filter and collapse icon listeners
        bindFilterListeners();
        bindCollapseIconListeners();
        
        // Expose function globally for the onclick event (printSingleOrder)
        window.printSingleOrder = printSingleOrder;
    }


    // Function to bind the AJAX form listener
    function bindFormListener(form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const itemId = this.getAttribute('data-item-id');
            const orderId = this.getAttribute('data-order-id');
            const selectElement = this.querySelector('select[name="status"]');
            const newStatus = selectElement.value;
            const spinner = this.querySelector('.loading-spinner');
            const statusBadge = document.querySelector(`.status-badge[data-item-id="${itemId}"]`);
            const orderCard = document.getElementById(`order-card-${orderId}`);

            // 1. Show Loading State
            spinner.classList.remove('d-none');
            this.querySelector('.update-btn').disabled = true;
            selectElement.disabled = true;

            // 2. Prepare Data for AJAX
            const formData = new FormData();
            formData.append('itemId', itemId);
            formData.append('orderId', orderId); 
            formData.append('status', newStatus);

            try {
                // 3. Send AJAX Request to updateItemStatus.php
                const response = await fetch('updateItemStatus.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                // 4. Handle Response
                if (result.success) {
                    // Update Item Status Badge
                    statusBadge.textContent = newStatus;
                    statusBadge.className = `status-badge status-${newStatus}`;
                    
                    // Update Overall Order Status (if returned)
                    if (result.orderStatus) {
                        const headerBadge = orderCard.querySelector('.card-header .badge');
                        orderCard.setAttribute('data-status', result.orderStatus);
                        
                        if(headerBadge) {
                            headerBadge.textContent = result.orderStatus;
                            headerBadge.className = `ms-3 badge rounded-pill status-Overall-${result.orderStatus} p-2 d-none d-md-inline`;
                        }
                    }
                    showAlert('success', `Item #${itemId} status updated to <strong>${newStatus}</strong> successfully.`);

                } else {
                    showAlert('danger', `Update failed: ${result.message || 'Unknown error occurred.'}`);
                }

            } catch (error) {
                console.error('AJAX Error:', error);
                showAlert('danger', `A network error occurred while updating status. Please try again.`);
            } finally {
                // 5. Hide Loading State regardless of success/failure
                spinner.classList.add('d-none');
                this.querySelector('.update-btn').disabled = false;
                selectElement.disabled = false;
                selectElement.value = newStatus; 
            }
        });
    }

    // Function to bind filter listeners
    function bindFilterListeners() {
        const filterButtons = document.querySelectorAll('.filter-btn');
        const orderCards = document.querySelectorAll('.card-order');
        
        // Find the currently active filter button based on the URL's date_range or the default 'All'
        const urlParams = new URLSearchParams(window.location.search);
        const currentFilter = urlParams.get('date_range') || 'all';

        // Set the appropriate filter button to active visually
        document.querySelector(`.filter-btn[data-filter="${currentFilter === 'all' ? 'All' : currentFilter}"]`)?.classList.add('active');


        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                const filterStatus = button.getAttribute('data-filter');

                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                orderCards.forEach(card => {
                    const cardStatus = card.getAttribute('data-status');
                    if (filterStatus === 'All' || cardStatus === filterStatus) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    }

    // Function to bind collapse icon rotation listeners
    function bindCollapseIconListeners() {
        const orderList = document.getElementById('order-list');
        orderList.querySelectorAll('.card-header').forEach(header => {
            const collapseTargetId = header.getAttribute('data-bs-target');
            const collapseElement = document.querySelector(collapseTargetId);
            const icon = header.querySelector('.toggle-icon');
            
            if (collapseElement) {
                collapseElement.addEventListener('show.bs.collapse', () => {
                    header.setAttribute('aria-expanded', 'true');
                    if (icon) icon.style.transform = 'rotate(0deg)';
                });
                collapseElement.addEventListener('hide.bs.collapse', () => {
                    header.setAttribute('aria-expanded', 'false');
                    if (icon) icon.style.transform = 'rotate(-90deg)';
                });
            }
            // Initial state set
            header.setAttribute('aria-expanded', 'false');
            if (icon) icon.style.transform = 'rotate(-90deg)';
        });
    }

    // Function to show transient alert messages
    function showAlert(type, message) {
        const messageContainer = document.getElementById('message-container');
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        messageContainer.innerHTML = alertHtml;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Initial setup
        initializeDOM();

        // Check if auto-print flag is set and trigger print dialog
        if (SHOULD_AUTOPRINT) {
             // Use a slight delay to ensure the browser has fully rendered the filtered list
            setTimeout(() => {
                window.print();
            }, 50); 
        }
    });
</script>

</body>
</html>
