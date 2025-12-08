<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $electionId = $_POST['election_id'];
    $candidateId = $_POST['candidate_id'];
    $studentId = $_SESSION['user_id'];

    // Double check if election is open
    $elections = getJSONData('elections.json');
    $isOpen = false;
    foreach ($elections as $e) {
        if ($e['id'] === $electionId && $e['status'] === 'Open') {
            $isOpen = true;
            break;
        }
    }
    if (!$isOpen)
        redirect('dashboard.php', 'Voting is closed.', 'error');

    // Double check if already voted
    $voters = getJSONData('voters.json');
    foreach ($voters as $v) {
        if ($v['election_id'] === $electionId && $v['student_id'] === $studentId) {
            redirect('dashboard.php', 'You have already voted.', 'error');
        }
    }

    // Record Vote (Anonymous)
    $votes = getJSONData('votes.json');
    $votes[] = [
        'election_id' => $electionId,
        'candidate_id' => $candidateId,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    saveJSONData('votes.json', $votes);

    // Record Voter (Tracking)
    $voters[] = [
        'election_id' => $electionId,
        'student_id' => $studentId,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    saveJSONData('voters.json', $voters);

    redirect('dashboard.php', 'Vote submitted successfully!');
}
?>