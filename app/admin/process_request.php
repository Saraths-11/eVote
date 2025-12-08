<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $electionId = $_POST['election_id'];
    $action = $_POST['action']; // Approve or Reject

    $candidates = getJSONData('candidates.json');
    foreach ($candidates as &$candidate) {
        if ($candidate['id'] === $id) {
            $candidate['status'] = ($action === 'Approve') ? 'Approved' : 'Rejected';
            break;
        }
    }

    saveJSONData('candidates.json', $candidates);
    redirect("manage_requests.php?id=$electionId", "Candidate request processed.");
}
?>