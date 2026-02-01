<?php
session_start();
include 'config.php';

// Access Control: Admin Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Perform deletion
    $stmt = $conn->prepare("DELETE FROM elections WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Success
            header("Location: manage_elections.php?msg=deleted");
            exit();
        } else {
            // Error
            echo "Error deleting record: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
} else {
    header("Location: manage_elections.php");
    exit();
}
?>