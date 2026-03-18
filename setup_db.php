<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

echo "<pre>";
echo "Starting FULL Database Setup...\n";

// 1. Create 'users' table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accountFullName VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    college_id VARCHAR(50) DEFAULT '',
    department VARCHAR(100),
    year VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_users)) {
    echo "Table 'users' confirmed.\n";
} else {
    echo "Error creating 'users': " . $conn->error . "\n";
}

// 2. Create 'elections' table
$sql_elections = "CREATE TABLE IF NOT EXISTS elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    registration_start DATETIME,
    registration_end DATETIME,
    nomination_start DATETIME,
    nomination_end DATETIME,
    election_start DATETIME,
    election_end DATETIME,
    status VARCHAR(50) DEFAULT 'Registering',
    voting_status VARCHAR(50) DEFAULT 'not_started',
    result_view_type VARCHAR(50) DEFAULT 'detailed',
    is_published TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_elections)) {
    echo "Table 'elections' confirmed.\n";
} else {
    echo "Error creating 'elections': " . $conn->error . "\n";
}

// 3. Create 'participants' table
$sql_participants = "CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    college_id VARCHAR(50),
    department VARCHAR(100),
    year VARCHAR(20),
    dob DATE,
    photo_path VARCHAR(255),
    proof_path VARCHAR(255),
    signature_path VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Pending',
    rejection_reason TEXT,
    removal_reason TEXT,
    removed_at DATETIME,
    cancellation_reason TEXT,
    cancelled_at DATETIME,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_participants)) {
    echo "Table 'participants' confirmed.\n";
} else {
    echo "Error creating 'participants': " . $conn->error . "\n";
}

// 4. Create 'votes' table
$sql_votes = "CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    student_id INT NOT NULL,
    candidate_id INT NOT NULL,
    college_id VARCHAR(50),
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (election_id, student_id),
    INDEX idx_candidate (candidate_id)
)";
if ($conn->query($sql_votes)) {
    echo "Table 'votes' confirmed.\n";
} else {
    echo "Error creating 'votes': " . $conn->error . "\n";
}

// 5. Create 'voting_logs' table
$sql_logs = "CREATE TABLE IF NOT EXISTS voting_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT,
    user_id INT,
    action VARCHAR(50),
    status VARCHAR(50),
    ip_address VARCHAR(50),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_logs)) {
    echo "Table 'voting_logs' confirmed.\n";
} else {
    echo "Error creating 'voting_logs': " . $conn->error . "\n";
}

// Seed Initial Admin User if empty
$check_admin = $conn->query("SELECT id FROM users WHERE role = 'admin'");
if ($check_admin && $check_admin->num_rows == 0) {
    $admin_pass = password_hash('2004', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (accountFullName, email, password, role) VALUES ('Administrator', 'sarath123@gmail.com', '$admin_pass', 'admin')");
    echo "Seed: Default Admin User created.\n";
}

echo "\nSetup Complete!\n";
echo "</pre>";
?>
