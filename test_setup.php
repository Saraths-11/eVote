<?php
include 'config.php';
$email = "nomination_test@mca.ajce.in";
$password = "Password@123";
$name = "Selenium Candidate";
$college_id = "88888";
$department = "MCA";
$year = "2";
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Check if exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Update
    $user = $res->fetch_assoc();
    $id = $user['id'];
    $conn->query("UPDATE users SET accountFullName='$name', password='$hashed', college_id='$college_id', department='$department', year='$year' WHERE id=$id");

    // Clear any existing nominations for this user to allow re-testing
    $conn->query("DELETE FROM participants WHERE user_id=$id");
} else {
    // Insert
    $conn->query("INSERT INTO users (accountFullName, email, password, role, college_id, department, year) VALUES ('$name', '$email', '$hashed', 'student', '$college_id', '$department', '$year')");
}
echo "Setup complete with password reset.";
?>