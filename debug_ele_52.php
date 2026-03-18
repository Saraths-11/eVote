<?php
include 'config.php';
$res = $conn->query("SELECT id, title, status, registration_status FROM elections WHERE id=52");
print_r($res->fetch_assoc());
?>