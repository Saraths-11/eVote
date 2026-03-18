<?php
include 'config.php';
$result = $conn->query("DESCRIBE notifications");
$schema = [];
while ($row = $result->fetch_assoc()) {
    $schema[] = $row;
}
file_put_contents('notifications_schema.json', json_encode($schema, JSON_PRETTY_PRINT));
?>
