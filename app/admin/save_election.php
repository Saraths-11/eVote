<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $elections = getJSONData('elections.json');

    $newElection = [
        'id' => uniqid('elec_'),
        'name' => sanitize($_POST['name']),
        'year' => sanitize($_POST['year']),
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'reg_start' => $_POST['reg_start'],
        'reg_end' => $_POST['reg_end'],
        'status' => 'Upcoming', // Upcoming, Open, Closed
        'poster' => ''
    ];

    // Handle File Upload
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES['poster']['name']);
        move_uploaded_file($_FILES['poster']['tmp_name'], $uploadDir . $fileName);
        $newElection['poster'] = $fileName;
    }

    $elections[] = $newElection;
    saveJSONData('elections.json', $elections);

    redirect('dashboard.php', 'Election created successfully!');
}
?>