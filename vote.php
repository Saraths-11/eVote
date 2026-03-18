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
$result = $stmt->get_result();
$election = $result ? $result->fetch_assoc() : null;

if (!$election) {
    die("Election not found.");
}

// Backend Enforcement: Voting Phase Check
if ($election['voting_status'] !== 'active') {
    // Exception: Allow view candidates if registration and nomination are closed
    if ($election['registration_status'] === 'closed' && $election['nomination_status'] === 'closed') {
        // Continue to allow viewing candidates only
    } else {
        header("Location: student_dashboard.php?error=voting_not_active");
        exit();
    }
}

// Voting eligibility logic
$v_status = $election['voting_status'];
$is_voting_active = ($v_status === 'active');
$voting_msg = "";
if ($v_status === 'not_started')
    $voting_msg = "Voting has not started yet.";
elseif ($v_status === 'ended')
    $voting_msg = "Voting period has ended.";





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
    $res = $check_vote->get_result();
    $voted = $res ? $res->fetch_assoc() : null;
}

// Check if success flag is set
if (isset($_SESSION['vote_success'])) {
    $success = "Your vote has been successfully submitted.";
    unset($_SESSION['vote_success']);
    $voted = true;
}

$error = '';

// Fetch Candidates - Only Approved and Active
$cand_stmt = $conn->prepare("SELECT id, name, college_id, department, year, photo_path FROM participants WHERE election_id = ? AND status = 'Approved'");

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

        .status-banner {
            padding: 1rem;
            border-radius: 0.75rem;
            text-align: center;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .banner-active {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .banner-pending {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fef08a;
        }

        .banner-ended {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .vote-btn:disabled {
            background: #f1f5f9;
            color: #cbd5e1;
            cursor: not-allowed;
            border: 1px solid #e2e8f0;
        }

        .candidate-card.disabled {
            opacity: 0.8;
            background: #fafafa;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Voting</div>
        <div class="nav-menu">
            <a href="student_dashboard.php" class="btn btn-secondary">Back</a>
        </div>
    </nav>

    <div class="container" style="max-width: 800px;">
        <div style="text-align: center; margin: 3rem 0;">
            <h1 style="font-size: 2.25rem; color: var(--dark); margin-bottom: 0.5rem;">
                <?php echo htmlspecialchars($election['title']); ?>
            </h1>
            <p style="color: #64748b;">
                <?php if ($is_voting_active && !$voted): ?>
                    Please select your candidate and confirm your vote.
                <?php else: ?>
                    Candidate List & Participation Details
                <?php endif; ?>
            </p>
        </div>

        <?php if ($v_status === 'active'): ?>
            <div class="status-banner banner-active">🟢 Voting is currently Active</div>
        <?php elseif ($v_status === 'not_started'): ?>
            <div class="status-banner banner-pending">⏳ Voting has not started (Registration Ended)</div>
        <?php else: ?>
            <div class="status-banner banner-ended">⏹️ Voting period has ended</div>
        <?php endif; ?>


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
                        <div class="candidate-card <?php echo (!$is_voting_active || $voted) ? 'disabled' : ''; ?>">
                            <img src="<?php echo $cand['photo_path']; ?>" class="candidate-img" alt="Candidate Photo">
                            <div>
                                <h3 style="margin: 0; font-size: 1.25rem;">
                                    <?php echo htmlspecialchars($cand['name']); ?>
                                </h3>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.3rem;">
                                    <span
                                        style="font-size: 0.75rem; background: #e0e7ff; color: #4338ca; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700;">
                                        ID: <?php echo htmlspecialchars($cand['college_id']); ?>
                                    </span>
                                    <span
                                        style="font-size: 0.75rem; background: #f1f5f9; color: #475569; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 600;">
                                        <?php echo htmlspecialchars($cand['department']); ?> |
                                        <?php echo htmlspecialchars($cand['year']); ?>
                                    </span>
                                    <span
                                        style="font-size: 0.75rem; background: #fef3c7; color: #92400e; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700;">
                                        Role: Candidate
                                    </span>
                                </div>


                            </div>
                            <?php if ($is_voting_active): ?>
                                <button type="submit" class="vote-btn" <?php echo $voted ? 'disabled' : ''; ?>>
                                    <?php echo $voted ? 'Voted' : 'Cast Vote'; ?>
                                </button>
                            <?php endif; ?>
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