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

// Database Connection via MYSQL_PUBLIC_URL (Railway)
$url = getenv("MYSQL_PUBLIC_URL") ?: "mysql://root:aztVINSZfkAHqMcsyXTXrBahbmDmmCjY@shortline.proxy.rlwy.net:14736/railway";

if (!$url) {
    die("MYSQL_PUBLIC_URL not set");
}

$db = parse_url($url);

if ($db === false) {
    die("Invalid database URL format.");
}

$host = $db['host'] ?? '';
$user = $db['user'] ?? '';
$pass = $db['pass'] ?? '';
$name = isset($db['path']) ? ltrim($db['path'], '/') : '';
$port = $db['port'] ?? 3306;

// Disable strict reporting for better compatibility
mysqli_report(MYSQLI_REPORT_OFF);

// Create connection
$conn = new mysqli($host, $user, $pass, $name, (int)$port);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error . " (Code: " . $conn->connect_errno . ")");
}

// Global check for mysqlnd (get_result support)
if (!function_exists('mysqli_stmt_get_result')) {
    die("CRITICAL ERROR: Your hosting does not support 'mysqli_stmt::get_result()'. If you are on Render, this should be pre-installed. Please check your PHP extensions.");
}
?>