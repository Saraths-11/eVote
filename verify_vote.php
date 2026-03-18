<?php
session_start();
include 'config.php';

// Access Control: Student Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['candidate'])) {
    header("Location: student_dashboard.php");
    exit();
}

$election_id = intval($_GET['id']);
$candidate_id = intval($_GET['candidate']);
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

// Check if voting window is active via Admin Control
if ($election['voting_status'] !== 'active') {
    die("Voting is not currently active for this election.");
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
if ($check_vote) {
    $check_vote->bind_param("ii", $election_id, $user_id);
    $check_vote->execute();
    $res = $check_vote->get_result();
    if ($res && $res->fetch_assoc()) {
        header("Location: vote.php?id=$election_id");
        exit();
    }
} else {
    // If table just created or structural issue, assume not voted but log error or handle gracefully
}

// Fetch Candidate Details
$cand_stmt = $conn->prepare("SELECT name, department, year, photo_path FROM participants WHERE id = ? AND election_id = ? AND status = 'Approved'");
$cand_stmt->bind_param("ii", $candidate_id, $election_id);
$cand_stmt->execute();
$res = $cand_stmt->get_result();
$candidate = $res ? $res->fetch_assoc() : null;

if (!$candidate) {
    die("Selected candidate is invalid or not approved.");
}

// Fetch Voter Details (College ID and Name from users table)
$user_stmt = $conn->prepare("SELECT accountFullName, college_id FROM users WHERE id = ?");
if (!$user_stmt) {
    die("Database Error (Prepare): " . $conn->error);
}
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$res = $user_stmt->get_result();
$user_data = $res ? $res->fetch_assoc() : null;
$db_student_name = $user_data['accountFullName'] ?? 'Unknown Student';
$college_id = $user_data['college_id'] ?? 'Not Set';

// Handle Proceed to Final Confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_vote'])) {
    if (!isset($_POST['terms_agree'])) {
        $error = "You must confirm the agreement checkbox.";
    } else {
        // Redirect to the secure confirmation page with password verification
        header("Location: confirm_vote.php?id=$election_id&candidate=$candidate_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Vote Verification - eVote</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f8fafc;
            font-family: 'Outfit', 'Inter', system-ui, -apple-system, sans-serif;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .verify-container {
            width: 100%;
            max-width: 520px;
            padding: 2rem;
        }

        .verify-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .verify-header {
            background: #fff;
            padding: 2.5rem 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .verify-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.5rem 0;
        }

        .verify-header p {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .section-title {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #f1f5f9;
        }

        .details-box {
            padding: 2rem;
        }

        .candidate-preview {
            display: flex;
            gap: 1.25rem;
            align-items: center;
            background: #fff;
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid #f1f5f9;
            margin-bottom: 2rem;
        }

        .cand-photo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f8fafc;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .voter-info {
            margin-bottom: 2rem;
            padding: 0 0.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .info-label {
            color: #64748b;
        }

        .info-value {
            font-weight: 600;
            color: #1e293b;
        }

        .confirm-checkbox {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            cursor: pointer;
            border: 1px solid #f1f5f9;
        }

        .confirm-checkbox input {
            margin-top: 0.2rem;
            cursor: pointer;
        }

        .confirm-checkbox span {
            font-size: 0.85rem;
            line-height: 1.4;
            color: #475569;
            font-weight: 500;
        }

        .action-btns {
            display: flex;
            gap: 0.75rem;
            padding: 0 2rem 2rem;
        }

        .btn {
            flex: 1;
            padding: 0.85rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
        }

        .btn-submit {
            background: #2563eb;
            color: white;
        }

        .btn-submit:hover:not(:disabled) {
            background: #1d4ed8;
        }

        .btn-submit:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn-back {
            background: #fff;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-back:hover {
            background: #f8fafc;
            color: #1e293b;
        }
    </style>
</head>

<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-header">
                <h1>Vote Verification</h1>
                <p>Please verify your selected participant before submitting your vote.</p>
            </div>

            <form method="POST">
                <div class="details-box">
                    <div class="section-title">Selection Review</div>
                    <div class="candidate-preview">
                        <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" class="cand-photo">
                        <div>
                            <div style="font-weight: 700; font-size: 1rem; color: #1e293b;">
                                <?php echo htmlspecialchars($candidate['name']); ?>
                            </div>
                            <div style="color: #64748b; font-size: 0.85rem; margin-top: 0.15rem;">
                                <?php echo htmlspecialchars($candidate['department']); ?> • Year
                                <?php echo $candidate['year']; ?>
                            </div>
                            <div
                                style="margin-top: 0.5rem; color: #2563eb; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.02em;">
                                <?php echo htmlspecialchars($election['title']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="section-title">Voter Details (Read-only)</div>
                    <div class="voter-info">
                        <div class="info-row">
                            <span class="info-label">Student Name</span>
                            <span class="info-value" style="color: #64748b;">
                                <?php echo htmlspecialchars($db_student_name); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">College ID</span>
                            <span class="info-value" style="color: #64748b;">
                                <?php echo htmlspecialchars($college_id); ?>
                            </span>
                        </div>
                    </div>

                    <label class="confirm-checkbox">
                        <input type="checkbox" name="terms_agree" id="terms_agree"
                            onchange="document.getElementById('submit_btn').disabled = !this.checked">
                        <span>I have verified my selection and confirm this as my final vote.</span>
                    </label>
                </div>

                <div class="action-btns">
                    <a href="vote.php?id=<?php echo $election_id; ?>" class="btn btn-back">Back</a>
                    <button type="submit" name="confirm_vote" id="submit_btn" class="btn btn-submit" disabled>Confirm
                        Vote</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>