<?php
include 'config.php';
$res = $conn->query("SELECT id, title, status FROM elections WHERE status NOT IN ('Completed', 'Closed')");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Title: " . $row['title'] . " | Status: " . $row['status'] . "\n";
}
?>