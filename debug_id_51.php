<?php
include 'config.php';
$res = $conn->query("SELECT * FROM elections WHERE id=51");
$row = $res->fetch_assoc();
if ($row) {
    foreach ($row as $k => $v) {
        echo "[$k] => '$v'\n";
    }
} else {
    echo "ID 51 NOT FOUND";
}
?>