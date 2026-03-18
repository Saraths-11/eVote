<?php
include 'config.php';
$res = $conn->query('DESCRIBE elections');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
echo "----\n";
$res = $conn->query('SELECT DISTINCT status FROM elections');
while($row = $res->fetch_assoc()) {
    echo 'Status: ' . $row['status'] . "\n";
}
?>
