<?php
include 'config.php';
$conn->query("UPDATE elections SET registration_start='2026-03-01 10:00:00', registration_end='2026-03-31 10:00:00', registration_status='open', status='active', voting_status='not_started', nomination_status='closed' WHERE status NOT IN ('Completed', 'Closed')");
echo "Updated completely.";
?>