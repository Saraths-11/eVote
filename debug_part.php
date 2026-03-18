<?php
include 'config.php';
$res = $conn->query("SELECT * FROM participants WHERE election_id=50 AND name='Selenium Candidate'");
print_r($res->fetch_assoc());
?>