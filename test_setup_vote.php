<?php
include 'config.php';

// 1. Approve all pending candidates for the latest Selenium election
$conn->query("UPDATE participants SET status='Approved' 
               WHERE election_id = (SELECT id FROM elections WHERE title LIKE 'Live Selenium%' ORDER BY id DESC LIMIT 1) 
               AND status='Pending'");

// 2. Set Election 52 to Voting Active
// Also ensure registration is closed and nomination period is over
$now = date('Y-m-d H:i:s');
$start_voting = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
$end_voting = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now

$conn->query("UPDATE elections SET 
    status='active', 
    voting_status='active', 
    registration_status='closed',
    nomination_status='closed',
    election_start='$start_voting', 
    election_end='$end_voting',
    visibleTo='students'
    WHERE title LIKE 'Live Selenium%'
    ORDER BY id DESC LIMIT 1");

echo "Voting setup complete for Election 52. Candidate approved and voting is LIVE.";
?>