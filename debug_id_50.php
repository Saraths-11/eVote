<?php
include 'config.php';
$res = $conn->query("SELECT * FROM elections WHERE id=50");
$row = $res->fetch_assoc();
if ($row) {
    echo "ID: " . $row['id'] . "\n";
    echo "TITLE: " . $row['title'] . "\n";
    echo "STATUS: " . $row['status'] . "\n";
} else {
    echo "ID 50 NOT FOUND";
}
?>