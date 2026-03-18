<?php
include 'config.php';
$conn->query("UPDATE elections SET status='Closed' WHERE status NOT IN ('Completed', 'Closed')");
echo "Rows affected: " . $conn->affected_rows;
?>