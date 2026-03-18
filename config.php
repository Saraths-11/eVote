<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Polyfill for str_ends_with (PHP < 8.0)
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}

// Database Configuration - Prefer Environment Variables (Render/Railway)
$db_host = getenv("DB_HOST") ?: "shortline.proxy.rlwy.net";
$db_user = getenv("DB_USER") ?: "root";
$db_pass = getenv("DB_PASS") ?: "aztVINSZfkAHqMcsyXTXrBahbmDmmCjY";
$db_name = getenv("DB_NAME") ?: "railway";
$db_port = getenv("DB_PORT") ?: 14736;

// Disable strict reporting for better compatibility
mysqli_report(MYSQLI_REPORT_OFF);

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error . " (Code: " . $conn->connect_errno . ")");
}

// Global check for mysqlnd (get_result support)
if (!function_exists('mysqli_stmt_get_result')) {
    die("CRITICAL ERROR: Your hosting does not support 'mysqli_stmt::get_result()'. If you are on Render, this should be pre-installed. Please check your PHP extensions.");
}
?>