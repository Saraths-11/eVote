<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// AUTO-FIX: Ensure votes table schema is correct (Standardize on student_id)
$conn->query("CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    student_id INT NOT NULL,
    candidate_id INT NOT NULL,
    college_id VARCHAR(50),
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (election_id, student_id)
)");

// If table exists with voter_id, rename it to student_id
$check_old = $conn->query("SHOW COLUMNS FROM votes LIKE 'voter_id'");
if ($check_old && $check_old->num_rows > 0) {
    $conn->query("ALTER TABLE votes CHANGE voter_id student_id INT NOT NULL");
}

// Ensure college_id exists
$check_college = $conn->query("SHOW COLUMNS FROM votes LIKE 'college_id'");
if ($check_college && $check_college->num_rows == 0) {
    $conn->query("ALTER TABLE votes ADD COLUMN college_id VARCHAR(50) AFTER candidate_id");
}

// STRICT IDENTITY FETCH: Get user details from DB
$user_id = $_SESSION['user_id']; // Trusted Session ID
$stmt_user = $conn->prepare("SELECT accountFullName, college_id, department, year FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$current_student = $stmt_user->get_result()->fetch_assoc();

if (!$current_student) {
    // Should not happen for logged in user, but handle safely
    session_destroy();
    header("Location: login.php");
    exit();
}

// Use DB values for display logic
$display_name_db = $current_student['accountFullName'];
$college_id_db = $current_student['college_id'];
$dept_db = $current_student['department'];
$year_db = $current_student['year'];


// Fetch Active & Upcoming Elections
$active_elections = [];
$sql = "SELECT * FROM elections WHERE status IN ('Upcoming', 'Registration Open', 'Registration Ended', 'Voting Active') ORDER BY election_start ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $active_elections[] = $row;
    }
}

// Fetch Completed Elections for Results (Only Published)
$completed_elections = [];
$sql_completed = "SELECT * FROM elections WHERE status = 'Completed' AND is_published = 1 ORDER BY election_end DESC";
$result_completed = $conn->query($sql_completed);
if ($result_completed) {
    while ($row = $result_completed->fetch_assoc()) {
        $completed_elections[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - eVote</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Add styles for progress bars if not in style.css */
        .progress-bar-bg {
            background: #f1f5f9;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar-fill {
            background: #3b82f6;
            height: 100%;
            transition: width 0.5s ease-in-out;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Student</div>
        <div class="nav-menu">
            <span>Welcome, <strong>
                    <?php
                    // Use DB Name directly - Full Name Display
                    echo htmlspecialchars($display_name_db);
                    ?>
                </strong></span>
            <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Logout</a>
        </div>
    </nav>

    <div class="dashboard-content">
        <div class="welcome-banner">
            <h2>Student Dashboard</h2>
            <p>Participate in active elections and view your history.</p>
        </div>



        <h3 style="margin-bottom: 1rem; color: var(--dark);">🗳️ Active Elections</h3>
        <div class="grid-cards">
            <?php if (count($active_elections) > 0): ?>
                <?php foreach ($active_elections as $election): ?>
                    <div class="card" style="padding: 1rem; max-width: 380px;">
                        <div style="margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                            <?php
                            $statusColor = '#6b7280';
                            if ($election['status'] == 'Voting Active')
                                $statusColor = '#10b981';
                            if ($election['status'] == 'Registration Open')
                                $statusColor = '#3b82f6';
                            if ($election['status'] == 'Registration Ended')
                                $statusColor = '#6b7280';
                            if ($election['status'] == 'Upcoming')
                                $statusColor = '#f59e0b';
                            ?>
                            <span
                                style="font-size: 0.7rem; font-weight: 700; color: <?php echo $statusColor; ?>; background: <?php echo $statusColor; ?>10; padding: 0.15rem 0.5rem; border-radius: 4px; text-transform: uppercase;">
                                <?php echo htmlspecialchars($election['status']); ?>
                            </span>
                        </div>
                        <h3 style="font-size: 1.1rem; margin-bottom: 0.4rem;">
                            <?php echo htmlspecialchars($election['title']); ?>
                        </h3>
                        <p
                            style="margin-bottom: 0.75rem; color: #666; font-size: 0.85rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($election['description']); ?>
                        </p>
                        <div
                            style="font-size: 0.8rem; color: #555; background: #fafafa; padding: 0.6rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #f0f0f0;">
                            <div style="margin-bottom: 0.2rem;"><strong>Starts:</strong>
                                <?php echo date('M d, h:i A', strtotime($election['election_start'])); ?></div>
                            <div><strong>Ends:</strong> <?php echo date('M d, h:i A', strtotime($election['election_end'])); ?>
                            </div>
                        </div>
                        <?php
                        $btn_text = 'View Details';
                        $btn_style = 'background: #64748b;'; // Default gray
                
                        if ($election['voting_status'] == 'active') {
                            $btn_text = 'Cast Your Vote Now';
                            $btn_style = 'background: #10b981;'; // Green
                        } elseif ($election['registration_status'] == 'open') {
                            $btn_text = 'Register Now';
                            $btn_style = 'background: #3b82f6;'; // Blue
                        } elseif ($election['registration_status'] == 'closed' && $election['voting_status'] == 'not_started') {
                            $btn_text = 'View Participants';
                            $btn_style = 'background: #64748b;'; // Gray
                        } elseif ($election['status'] == 'Upcoming') {
                            $btn_text = 'Preview Election';
                            $btn_style = 'background: #f59e0b;'; // Orange
                        }
                        ?>
                        <a href="enroll.php?id=<?php echo $election['id']; ?>" class="btn btn-primary"
                            style="padding: 0.6rem; font-size: 0.85rem; display: block; text-align: center; border-radius: 6px; <?php echo $btn_style; ?> border: none; font-weight: 600;">
                            <?php echo $btn_text; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card" style="grid-column: 1 / -1; text-align: center; color: #666;">
                    <h3>No Active Elections</h3>
                    <p>There are currently no elections open for voting.</p>
                </div>
            <?php endif; ?>
        </div>

        <h3 style="margin-bottom: 1rem; color: var(--dark); margin-top: 3rem;">🏆 Election Results</h3>
        <div class="grid-cards">
            <?php if (count($completed_elections) > 0): ?>
                <?php foreach ($completed_elections as $election): ?>
                    <div class="card" style="padding: 1rem; max-width: 380px;">
                        <div style="margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                            <span
                                style="font-size: 0.7rem; font-weight: 700; color: #374151; background: #f3f4f6; padding: 0.15rem 0.5rem; border-radius: 4px; text-transform: uppercase;">
                                Completed
                            </span>
                            <span style="font-size: 0.75rem; color: #6b7280;">
                                <?php echo date('M d, Y', strtotime($election['election_end'])); ?>
                            </span>
                        </div>
                        <h3 style="font-size: 1.1rem; margin-bottom: 0.4rem;">
                            <?php echo htmlspecialchars($election['title']); ?>
                        </h3>

                        <p
                            style="margin-bottom: 0.75rem; color: #666; font-size: 0.85rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($election['description']); ?>
                        </p>
                        <div
                            style="font-size: 0.8rem; color: #555; background: #fafafa; padding: 0.6rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #f0f0f0;">
                            <div style="margin-bottom: 0.2rem;"><strong>Starts:</strong>
                                <?php echo date('M d, h:i A', strtotime($election['election_start'])); ?></div>
                            <div><strong>Ends:</strong> <?php echo date('M d, h:i A', strtotime($election['election_end'])); ?>
                            </div>
                        </div>

                        <a href="view_results.php?id=<?php echo $election['id']; ?>" class="btn btn-primary"
                            style="padding: 0.6rem; font-size: 0.85rem; display: block; text-align: center; border-radius: 6px; background: #4f46e5; border: none; font-weight: 600;">
                            View Final Result
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card" style="grid-column: 1 / -1; text-align: center; color: #666;">
                    <p>No published election results yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>