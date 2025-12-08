<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $candidates = getJSONData('candidates.json');

    $newCandidate = [
        'id' => uniqid('cand_'),
        'election_id' => $_POST['election_id'],
        'student_id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'], // Using session name for consistency
        'college_id' => sanitize($_POST['college_id']),
        'department' => sanitize($_POST['department']),
        'year' => sanitize($_POST['year']),
        'dob' => $_POST['dob'],
        'gender' => $_POST['gender'],
        'status' => 'Pending',
        'photo' => '',
        'proof' => ''
    ];

    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $fileName = time() . '_photo_' . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName);
        $newCandidate['photo'] = $fileName;
    }

    if (isset($_FILES['proof']) && $_FILES['proof']['error'] == 0) {
        $fileName = time() . '_proof_' . basename($_FILES['proof']['name']);
        move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $fileName);
        $newCandidate['proof'] = $fileName;
    }

    $candidates[] = $newCandidate;
    saveJSONData('candidates.json', $candidates);

    redirect('dashboard.php', 'Registration submitted for verification.');
}
?>