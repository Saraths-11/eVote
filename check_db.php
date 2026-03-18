<?php
include 'config.php';
$res = $conn->query("SHOW COLUMNS FROM elections");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "----\n";
$res = $conn->query("SHOW COLUMNS FROM participants");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>