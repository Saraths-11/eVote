<?php
if (function_exists('mysqli_stmt_get_result')) {
    echo "mysqlnd is enabled";
} else {
    echo "mysqlnd is NOT enabled. Using bind_result fallback.";
}
?>
