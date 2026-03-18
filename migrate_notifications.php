<?php
include 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_notif (user_id, notification_id)
)";

if ($conn->query($sql)) {
    echo "Table notification_reads created or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
