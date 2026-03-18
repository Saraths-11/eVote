<?php
include 'config.php';

$output = "Database: " . $db_name . "\n";
$res = $conn->query("SELECT id, title, status, created_at FROM elections ORDER BY created_at DESC LIMIT 1");

if ($res && $row = $res->fetch_assoc()) {
    $output .= "LATEST RECORD FOUND:\n";
    $output .= "ID: " . $row['id'] . "\n";
    $output .= "Title: " . $row['title'] . "\n";
    $output .= "Status: " . $row['status'] . "\n";
    $output .= "Created At: " . $row['created_at'] . "\n";
} else {
    $output .= "No elections found in the database yet.\n";
}

file_put_contents('db_status_report.txt', $output);
?>