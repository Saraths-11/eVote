<?php
include 'config.php';
$res = $conn->query("SELECT id, email, accountFullName FROM users WHERE email='nomination_test@mca.ajce.in'");
print_r($res->fetch_assoc());
?>