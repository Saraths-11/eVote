<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

echo "<pre>";
echo "Starting Database Setup...\n";

// 1. Create elections table columns if missing
$check = $conn->query("SHOW COLUMNS FROM elections LIKE 'result_view_type'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE elections ADD COLUMN result_view_type VARCHAR(50) DEFAULT 'detailed'");
}
$check = $conn->query("SHOW COLUMNS FROM elections LIKE 'is_published'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE elections ADD COLUMN is_published TINYINT(1) DEFAULT 0");
}

// 2. Create votes table
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

// 3. Create voting_logs table
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

// 4. Ensure index on votes
$res = $conn->query("SHOW INDEX FROM votes WHERE Key_name = 'idx_candidate'");
if ($res && $res->num_rows == 0) {
    if ($conn->query("ALTER TABLE votes ADD INDEX idx_candidate (candidate_id)")) {
        echo "Index 'idx_candidate' added.\n";
    }
}

echo "Setup Complete!\n";
echo "</pre>";
?>
