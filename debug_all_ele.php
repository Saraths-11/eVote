<?php
include 'config.php';
$res = $conn->query("SELECT id, title, created_at FROM elections ORDER BY id DESC LIMIT 10");
echo "Recent Elections:\n";
while ($row = $res->fetch_assoc()) {
    echo "- ID: " . $row['id'] . " | Title: '" . $row['title'] . "' | Created: " . $row['created_at'] . "\n";
}
?>