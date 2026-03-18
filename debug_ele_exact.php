<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$res = $conn->query("SELECT * FROM elections WHERE id=50");
if (!$res) {
    die("Query failed: " . $conn->error);
}
$row = $res->fetch_assoc();
if (!$row) {
    die("No row found for ID 50.");
}
foreach ($row as $k => $v) {
    echo "[$k] => '$v'\n";
}
?>