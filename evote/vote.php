<?php
session_start();
include 'config.php';

// Access Control: Student Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: student_dashboard.php");
    exit();
}

$election_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Fetch Election Details
$stmt = $conn->prepare("SELECT * FROM elections WHERE id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

if (!$election) {
    die("Election not found.");
}

// Check if voting window is active via Admin Control
if ($election['voting_status'] === 'not_started') {
    die("Voting has not been started by the Admin yet. Scheduled: " . date('M d, Y h:i A', strtotime($election['election_start'])));
} elseif ($election['voting_status'] === 'ended') {
    die("Voting has ended for this election. Closed by Admin on " . date('M d, Y h:i A'));
} elseif ($election['voting_status'] !== 'active') {
    die("Voting is currently unavailable.");
}

// Ensure votes table exists
$conn->query("CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    student_id INT NOT NULL,
    candidate_id INT NOT NULL,
    college_id VARCHAR(50),
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (election_id, student_id)
)");

// Check if user has already voted
$check_vote = $conn->prepare("SELECT id FROM votes WHERE election_id = ? AND student_id = ?");
$voted = null;
if ($check_vote) {
    $check_vote->bind_param("ii", $election_id, $user_id);
    $check_vote->execute();
    $voted = $check_vote->get_result()->fetch_assoc();
}

// Check if success flag is set
if (isset($_SESSION['vote_success'])) {
    $success = "Your vote has been successfully submitted.";
    unset($_SESSION['vote_success']);
    $voted = true;
}

$error = '';

// Fetch Candidates
$cand_stmt = $conn->prepare("SELECT id, name, department, year, photo_path FROM participants WHERE election_id = ? AND status = 'Approved'");
$cand_stmt->bind_param("i", $election_id);
$cand_stmt->execute();
$candidates = $cand_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Cast Vote -
        <?php echo htmlspecialchars($election['title']); ?>
    </title>
    <link rel="stylesheet" href="style.css">
    <style>
        .candidate-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .candidate-card:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .candidate-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f1f5f9;
        }

        .vote-btn {
            margin-left: auto;
            background: #f1f5f9;
            color: #64748b;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .candidate-card:hover .vote-btn {
            background: var(--primary);
            color: white;
        }

        .voted-msg {
            text-align: center;
            padding: 3rem;
            background: #f0fdf4;
            border-radius: 1.5rem;
            border: 1px solid #bbf7d0;
            margin-top: 2rem;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Voting</div>
        <div class="nav-menu">
            <a href="enroll.php?id=<?php echo $election_id; ?>" class="btn btn-secondary">Back</a>
        </div>
    </nav>

    <div class="container" style="max-width: 800px;">
        <div style="text-align: center; margin: 3rem 0;">
            <h1 style="font-size: 2.25rem; color: var(--dark); margin-bottom: 0.5rem;">
                <?php echo htmlspecialchars($election['title']); ?>
            </h1>
            <p style="color: #64748b;">Please select your candidate and confirm your vote.</p>
        </div>

        <?php if ($voted && !empty($success)): ?>
            <div class="voted-msg">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h2 style="color: #166534; margin-bottom: 0.5rem;"><?php echo $success; ?></h2>
                <p style="color: #166534;">Thank you for participating in the democratic process.</p>
                <a href="student_dashboard.php" class="btn btn-primary"
                    style="display: inline-block; margin-top: 1.5rem;">Return to Dashboard</a>
            </div>
        <?php elseif ($voted): ?>
            <div class="voted-msg" style="background: #f8fafc; border-color: #e2e8f0;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🗳️</div>
                <h2 style="color: #475569; margin-bottom: 0.5rem;">You have already voted</h2>
                <p style="color: #64748b;">Your vote has been recorded and finalized for this election.</p>
                <a href="student_dashboard.php" class="btn btn-primary"
                    style="display: inline-block; margin-top: 1.5rem;">Go Back</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="candidate-list">
                <?php while ($cand = $candidates->fetch_assoc()): ?>
                    <form method="GET" action="verify_vote.php">
                        <input type="hidden" name="id" value="<?php echo $election_id; ?>">
                        <input type="hidden" name="candidate" value="<?php echo $cand['id']; ?>">
                        <div class="candidate-card">
                            <img src="<?php echo $cand['photo_path']; ?>" class="candidate-img" alt="Candidate Photo">
                            <div>
                                <h3 style="margin: 0; font-size: 1.25rem;">
                                    <?php echo htmlspecialchars($cand['name']); ?>
                                </h3>
                                <p style="margin: 0.2rem 0 0; color: #64748b; font-size: 0.9rem;">
                                    <?php echo $cand['department']; ?> |
                                    <?php echo $cand['year']; ?>
                                </p>
                            </div>
                            <button type="submit" class="vote-btn">Cast Vote</button>
                        </div>
                    </form>
                <?php endwhile; ?>

                <?php if ($candidates->num_rows === 0): ?>
                    <div style="text-align: center; padding: 4rem; color: #94a3b8;">
                        <p>No approved candidates found for this election.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>