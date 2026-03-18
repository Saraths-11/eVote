<?php
include 'config.php';
$res = $conn->query("SELECT id, title, status, voting_status, visibleTo FROM elections WHERE id=50");
print_r($res->fetch_assoc());
?>