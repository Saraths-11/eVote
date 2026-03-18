<?php
include 'config.php';
$res = $conn->query("SELECT MAX(id) as max_id FROM elections");
$row = $res->fetch_assoc();
echo "MAX ID: " . $row['max_id'];
?>