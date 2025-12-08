<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $status = $_POST['status'];

    $elections = getJSONData('elections.json');
    foreach ($elections as &$election) {
        if ($election['id'] === $id) {
            $election['status'] = $status;
            break;
        }
    }

    saveJSONData('elections.json', $elections);
    redirect("manage_election.php?id=$id", "Election status updated to $status.");
}
?>