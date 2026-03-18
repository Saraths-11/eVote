<?php
include 'config.php';

// Add nomination_status column if it doesn't exist
$sql = "ALTER TABLE elections ADD COLUMN IF NOT EXISTS nomination_status ENUM('not_started', 'open', 'closed') DEFAULT 'not_started'";
if ($conn->query($sql)) {
    echo "Column nomination_status added successfully or already exists.\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}

// Ensure participants table has status 'Cancelled'
// (It seems it already does based on view_participants.php)
?>