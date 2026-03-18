<?php
include 'config.php';
$res = $conn->query("SELECT id, title, status, voting_status, visibleTo FROM elections WHERE title LIKE '%Selenium%'");
echo "Selenium Elections:\n";
while ($row = $res->fetch_assoc()) {
    echo "- ID: " . $row['id'] . " | Title: " . $row['title'] . " | Status: " . $row['status'] . " | Voting: " . $row['voting_status'] . " | VisibleTo: " . $row['visibleTo'] . "\n";
}
?>