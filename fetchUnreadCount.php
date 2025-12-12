<?php
session_start();
include 'config.php';
$userId = $_SESSION['id'];
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS unread FROM notifications WHERE userId=? AND isRead=0");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
echo json_encode(['unread' => $row['unread'] ?? 0]);
?>
