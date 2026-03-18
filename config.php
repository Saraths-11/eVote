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
$db_host = 'sql101.infinityfree.com';
$db_user = 'if0_41422050';
$db_pass = 'sarathsk2004';
$db_name = 'if0_41422050_evote';

// Disable strict reporting for InfinityFree compatibility
mysqli_report(MYSQLI_REPORT_OFF);

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error . " (Code: " . $conn->connect_errno . ")");
}

// Global check for mysqlnd (get_result support)
if (!function_exists('mysqli_stmt_get_result')) {
    die("CRITICAL ERROR: Your hosting does not support 'mysqli_stmt::get_result()'. Please enable the 'mysqlnd' driver in your PHP settings (InfinityFree -> Alter PHP Config -> check mysqlnd).");
}
?>