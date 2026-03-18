<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$email = $input['email'] ?? '';
$name = $input['name'] ?? '';
$googleUid = $input['uid'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Define Roles based on Email
$role = '';
if ($email === 'sarath123@gmail.com') {
    $role = 'admin';
} elseif (str_ends_with($email, '@amaljyothi.ac.in')) {
    $role = 'faculty';
} elseif (str_ends_with($email, '@mca.ajce.in')) {
    $role = 'student';
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized email domain. Faculty: @amaljyothi.ac.in, Students: @mca.ajce.in']);
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT id, accountFullName, role, college_id, department, year FROM users WHERE email = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists, log them in
    $user = $result->fetch_assoc();

    // STRICT IDENTITY PROTECTION: 
    // Do NOT update the partial name from Google/Frontend. 
    // The name in the database is the Single Source of Truth set during registration.
    // We ignore $name from input for existing users to preventing overwriting "Sarath S Kumar" with "sarathskumar2028".
    if (!empty($user['accountFullName'])) {
        // Log if there's a mismatch for debugging, but DO NOT UPDATE
        // error_log("Identity Mismatch Ignored: DB=" . $user['accountFullName'] . ", Input=" . $name);
    }

    // Enforce Faculty Role for existing users if domain matches
    if (str_ends_with($email, '@amaljyothi.ac.in') && $user['role'] !== 'faculty') {
        $update_role = $conn->prepare("UPDATE users SET role = 'faculty' WHERE id = ?");
        if ($update_role) {
            $update_role->bind_param("i", $user['id']);
            $update_role->execute();
            $update_role->close();
            $user['role'] = 'faculty';
        }
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['accountFullName'];
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $user['role']; // Trust DB role
    $_SESSION['college_id'] = $user['college_id'];
    $_SESSION['department'] = $user['department'];
    $_SESSION['year'] = $user['year'];

    // Security enforcement for Admin
    if ($email === 'sarath123@gmail.com') {
        $_SESSION['role'] = 'admin';
        $_SESSION['user_id'] = 0; // Special ID for main admin
    }

    $redirect = 'student_dashboard.php';
    if ($_SESSION['role'] === 'admin')
        $redirect = 'admin_dashboard.php';
    if ($_SESSION['role'] === 'faculty')
        $redirect = 'faculty_dashboard.php';

    echo json_encode(['success' => true, 'redirect' => $redirect]);
} else {
    // User does not exist, create new account
    // Password is not needed for Google Auth, but DB schema likely requires it.
    // We will generate a random secure password or header.

    $random_pass = bin2hex(random_bytes(16));
    $hashed_password = password_hash($random_pass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (accountFullName, email, password, role) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database prepare (insert) failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;

        if ($email === 'sarath123@gmail.com') {
            $_SESSION['user_id'] = 0;
        }

        $redirect = 'student_dashboard.php';
        if ($role === 'admin')
            $redirect = 'admin_dashboard.php';
        if ($role === 'faculty')
            $redirect = 'faculty_dashboard.php';

        echo json_encode(['success' => true, 'redirect' => $redirect]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}
$stmt->close();
?>