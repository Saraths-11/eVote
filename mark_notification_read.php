<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: student_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = intval($_GET['id']);

$stmt = $conn->prepare("INSERT IGNORE INTO notification_reads (user_id, notification_id) VALUES (?, ?)");
$stmt->bind_param("ii", $user_id, $notification_id);
$stmt->execute();

header("Location: student_dashboard.php");
exit();
?>
