<?php
session_start();
include 'config.php';
$_SESSION['user_id'] = 12; // nomination_test@mca.ajce.in
$_SESSION['role'] = 'student';

// Use same query as dashboard
$sql_active = "SELECT * FROM elections 
               WHERE (visibleTo = 'students' AND status NOT IN ('Completed', 'Closed'))
               OR (status IN ('Completed', 'Closed') AND is_published = 1)
               ORDER BY 
               CASE 
                 WHEN status NOT IN ('Completed', 'Closed') THEN 0 
                 ELSE 1 
               END, 
               election_end DESC";
$active_result = $conn->query($sql_active);

echo "Found Elections: \n";
while ($row = $active_result->fetch_assoc()) {
    echo "- " . $row['title'] . " (Status: " . $row['status'] . ", Voting: " . $row['voting_status'] . ")\n";
}
?>