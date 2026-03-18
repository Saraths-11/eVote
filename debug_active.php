<?php
include 'config.php';
$res = $conn->query("SELECT id, title, status FROM elections WHERE status NOT IN ('Completed', 'Closed')");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>