<?php
include 'config.php';
$res = $conn->query("SELECT accountFullName, college_id, department, year FROM users ORDER BY id DESC LIMIT 1");
print_r($res->fetch_assoc());
?>