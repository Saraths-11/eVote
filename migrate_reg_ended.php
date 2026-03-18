<?php
include 'config.php';
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS registration_ended TINYINT(1) DEFAULT 0");
echo "Migration successful: registration_ended column added to elections table.";
?>
