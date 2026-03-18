<?php
include 'config.php';
$res = $conn->query("SELECT * FROM participants ORDER BY id DESC LIMIT 5");
echo "Recent Participants:\n";
while ($row = $res->fetch_assoc()) {
    echo "- ID: " . $row['id'] . " | Name: " . $row['name'] . " | ElectionID: " . $row['election_id'] . "\n";
}
?>