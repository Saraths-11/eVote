<?php
include 'config.php';
// One-time fix for existing elections
$conn->query("UPDATE elections SET status = 'active' WHERE is_published = 1 AND status != 'Completed'");
echo "Existing published elections have been set to 'active'.";
?>