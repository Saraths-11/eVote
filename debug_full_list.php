<?php
include 'config.php';
$res = $conn->query("SELECT id, title FROM elections");
echo "All Election IDs:\n";
while ($row = $res->fetch_assoc()) {
    echo "- " . $row['id'] . ": " . $row['title'] . "\n";
}
?>