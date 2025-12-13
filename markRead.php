<?php
session_start();
include 'config.php';

if(!isset($_SESSION['id'])) {
    exit('Not logged in');
}

$userId = $_SESSION['id'];

$stmt = mysqli_prepare($conn, "UPDATE notifications SET isRead=1 WHERE userId=? AND isRead=0");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo "success";
?>