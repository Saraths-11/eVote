<?php
// config.php
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'evote_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select database if it exists, otherwise we might be in install mode
$conn->select_db($db_name);
?>