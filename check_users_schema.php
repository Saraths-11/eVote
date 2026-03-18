<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

echo "Database Host: " . $db_host . "\n";
echo "Attempting connection...\n";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connection successful!\n";

$result = $conn->query("DESCRIBE users");
if (!$result) {
    die("Query failed: " . $conn->error);
}

$schema = [];
while ($row = $result->fetch_assoc()) {
    $schema[] = $row;
}
echo json_encode($schema, JSON_PRETTY_PRINT);
?>
