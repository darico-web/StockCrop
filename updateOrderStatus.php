<?php
include 'config.php';

if (isset($_POST['orderId'], $_POST['status'])) {
    $orderId = $_POST['orderId'];
    $status = $_POST['status'];

    // Update order status
    $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $orderId);
    mysqli_stmt_execute($stmt);

    // Get the customer ID
    $stmt2 = mysqli_prepare($conn, "SELECT customerId FROM orders WHERE id = ?");
    mysqli_stmt_bind_param($stmt2, "i", $orderId);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_bind_result($stmt2, $customerId);
    mysqli_stmt_fetch($stmt2);
    mysqli_stmt_close($stmt2);

    // If delivered, create a notification
    if ($status === "Delivered") {
        $message = "Your order #$orderId has been delivered! Thank you for shopping with us.";

        $stmt3 = mysqli_prepare($conn, 
            "INSERT INTO notifications (userId, orderId, message) VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt3, "iis", $customerId, $orderId, $message);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);
    }

    echo "Status updated successfully.";
}
?>
