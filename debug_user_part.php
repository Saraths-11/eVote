<?php
include 'config.php';
$res = $conn->query("SELECT * FROM participants WHERE user_id=12");
echo "User 12 Participants:\n";
while ($row = $res->fetch_assoc()) {
    echo "- ID: " . $row['id'] . " | ElectionID: " . $row['election_id'] . " | Status: " . $row['status'] . "\n";
}
?>