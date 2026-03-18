<?php
include 'config.php';

// Add missing columns if they don't exist
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS createdBy VARCHAR(50) DEFAULT 'admin'");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS visibleTo VARCHAR(50) DEFAULT 'students'");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS is_published TINYINT(1) DEFAULT 0");

echo "Schema updated successfully.";
?>