<?php
include 'config.php';

// Add Nomination Cancellation columns
$sql1 = "ALTER TABLE elections ADD COLUMN nomination_cancellation_start DATETIME NULL AFTER registration_end";
$sql2 = "ALTER TABLE elections ADD COLUMN nomination_cancellation_end DATETIME NULL AFTER nomination_cancellation_start";

if ($conn->query($sql1) === TRUE) {
    echo "Column nomination_cancellation_start added successfully.<br>";
} else {
    echo "Error adding column nomination_cancellation_start: " . $conn->error . "<br>";
}

if ($conn->query($sql2) === TRUE) {
    echo "Column nomination_cancellation_end added successfully.<br>";
} else {
    echo "Error adding column nomination_cancellation_end: " . $conn->error . "<br>";
}

echo "Migration V3 completed.";
?>