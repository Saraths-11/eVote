<?php
include 'config.php';

// Add rejection_reason column
$resReason = $conn->query("SHOW COLUMNS FROM participants LIKE 'rejection_reason'");
if ($resReason->num_rows == 0) {
    if ($conn->query("ALTER TABLE participants ADD COLUMN rejection_reason TEXT AFTER status")) {
        echo "Column 'rejection_reason' added successfully.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'rejection_reason' already exists.<br>";
}

$conn->close();
?>