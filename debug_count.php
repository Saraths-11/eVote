<?php
include 'config.php';
$res = $conn->query("SELECT COUNT(*) as total FROM elections");
$row = $res->fetch_assoc();
echo "Total Elections: " . $row['total'];
?>