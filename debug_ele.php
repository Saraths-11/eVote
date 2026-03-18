<?php
include 'config.php';
$res = $conn->query("SELECT id, title, status, voting_status, is_published FROM elections ORDER BY id DESC LIMIT 3");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>