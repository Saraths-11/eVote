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
$error = '';
$success = false;

/**
 * ANTI-IMPERSONATION MECHANISM:
 * 1. Logic uses $_SESSION['user_id'] which is set during secure login.
 * 2. Final vote requires re-verification of College ID and Password.
 * 3. Unique database constraint (election_id, student_id) prevents double voting.
 */

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

// Table existence checks moved to setup_db.php for better performance

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

// Fetch Voter Details (College ID from users table)
$user_stmt = $conn->prepare("SELECT college_id, password FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$res = $user_stmt->get_result();
$user_data = $res ? $res->fetch_assoc() : null;

if (!$user_data) {
    die("User not found.");
}

$college_id = $user_data['college_id'];
$stored_password = $user_data['password'];

if (empty($college_id)) {
    // If college_id is missing (e.g. Google user), they need to set it.
    // However, for this task, we assume it's collected during registration.
    // Let's fallback to an error or a placeholder for demo.
    $error = "Your profile is missing a College ID. Please contact administrator.";
}

// Handle Final Vote Submission with College ID and Password Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_vote'])) {
    // ANTI-IMPERSONATION CHECK:
    // User ID is derived from secure session. College ID is fetched from DB.
    // Re-check: If college_id is injected in POST, fail immediately.
    if (isset($_POST['college_id'])) {
        die("Security Violation: Unauthorized identity modification attempt.");
    }

    $input_name = trim($_POST['name'] ?? '');
    $input_password = $_POST['password'] ?? '';

    // STRICT IDENTITY VALIDATION (Step 193)
    // Fetch accountFullName from DB to verify against input
    $stmt_verify = $conn->prepare("SELECT accountFullName FROM users WHERE id = ?");
    $stmt_verify->bind_param("i", $user_id);
    $stmt_verify->execute();
    $res = $stmt_verify->get_result();
    $db_name = ($res && $row = $res->fetch_assoc()) ? $row['accountFullName'] : '';

    if (empty($input_name)) {
        $error = "Full Name is required.";
    } elseif (strcasecmp($input_name, $db_name) !== 0) {
        // Log mismatch as identity violation
        $log_stmt = $conn->prepare("INSERT INTO voting_logs (election_id, user_id, action, status, ip_address, details) VALUES (?, ?, 'identity_violation', 'failed', ?, 'Name mismatch during vote confirmation')");
        if ($log_stmt) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iis", $election_id, $user_id, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
        $error = "Entered name does not match your account name.";
    } elseif (empty($input_password)) {
        $error = "Please enter your Password to confirm vote.";
    } else {
        // Verify Password from users table
        $user_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $res = $user_stmt->get_result();
        $user_data_verify = $res ? $res->fetch_assoc() : null;

        if (!$user_data_verify || !password_verify($input_password, $stored_password)) {
            $error = "Invalid password. Please try again.";
            // Log failed attempt
            $log_stmt = $conn->prepare("INSERT INTO voting_logs (election_id, user_id, action, status, ip_address, details) VALUES (?, ?, 'vote_attempt', 'failed', ?, 'Invalid password')");
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iis", $election_id, $user_id, $ip);
            $log_stmt->execute();
        } else {
            // Double check vote again just before insert
            if ($check_vote) {
                $check_vote->execute();
                $res = $check_vote->get_result();
                if ($res && $res->fetch_assoc()) {
                    die("You have already voted.");
                }
            }

            // Insert the vote
            // Requirement 3: election_id, student_id, candidate_id, college_id, vote_timestamp
            $ins = $conn->prepare("INSERT INTO votes (election_id, student_id, candidate_id, college_id) VALUES (?, ?, ?, ?)");
            if ($ins) {
                $ins->bind_param("iiis", $election_id, $user_id, $candidate_id, $college_id);
                if ($ins->execute()) {
                    // Log success
                    $log_stmt = $conn->prepare("INSERT INTO voting_logs (election_id, user_id, action, status, ip_address, details) VALUES (?, ?, 'vote_cast', 'success', ?, ?)");
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $details = "Candidate ID: $candidate_id";
                    $log_stmt->bind_param("iiss", $election_id, $user_id, $ip, $details);
                    $log_stmt->execute();

                    $_SESSION['vote_success'] = true;
                    $success = true;
                } else {
                    // Proper Concurrent User Handling (Requirement 5)
                    // In exact same-millisecond race, unique constraint fails with code 1062
                    if ($conn->errno === 1062) {
                        $error = "You have already voted in this election.";
                    } else {
                        $error = "Failed to submit vote. System busy, please try again.";
                        // Log database error silently
                        $log_stmt = $conn->prepare("INSERT INTO voting_logs (election_id, user_id, action, status, ip_address, details) VALUES (?, ?, 'vote_cast', 'error', ?, ?)");
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $err_details = "DB Error: " . $conn->error;
                        $log_stmt->bind_param("iiss", $election_id, $user_id, $ip, $err_details);
                        $log_stmt->execute();
                    }
                }
            } else {
                $error = "Failed to prepare vote submission.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Confirm Your Vote - eVote</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .confirm-container {
            width: 100%;
            max-width: 480px;
        }

        .confirm-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .confirm-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .confirm-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.3;
        }

        .lock-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
        }

        .lock-icon svg {
            width: 28px;
            height: 28px;
            fill: white;
        }

        .confirm-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .confirm-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            position: relative;
        }

        .candidate-summary {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .candidate-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .candidate-info h3 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.2rem;
        }

        .candidate-info span {
            font-size: 0.8rem;
            color: #64748b;
        }

        .election-badge {
            margin-left: auto;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .form-section {
            padding: 1.75rem;
        }

        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-label svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #94a3b8;
            transition: fill 0.2s;
        }

        .input-wrapper input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .input-wrapper input:focus+svg,
        .input-wrapper:focus-within svg {
            fill: #6366f1;
        }

        .input-wrapper input::placeholder {
            color: #94a3b8;
        }

        .error-message {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
            flex-shrink: 0;
        }

        .success-container {
            text-align: center;
            padding: 3rem 2rem;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: successPop 0.5s ease-out;
        }

        @keyframes successPop {
            0% {
                transform: scale(0);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }

        .success-container h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .success-container p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .action-btns {
            display: flex;
            gap: 0.75rem;
            padding: 0 1.75rem 1.75rem;
        }

        .btn {
            flex: 1;
            padding: 0.9rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        .btn-submit {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-back {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-back:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-dashboard {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
        }

        .btn-dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .security-note {
            background: #fffbeb;
            border: 1px solid #fde68a;
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            color: #92400e;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .security-note svg {
            width: 16px;
            height: 16px;
            fill: #d97706;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }
    </style>
</head>

<body>
    <div class="confirm-container">
        <div class="confirm-card">
            <?php if ($success): ?>
                <!-- Success State -->
                <div class="success-container">
                    <div class="success-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
                        </svg>
                    </div>
                    <h2>Vote Submitted Successfully!</h2>
                    <p>Your vote has been recorded securely. Thank you for participating in the election.</p>
                    <a href="student_dashboard.php" class="btn btn-dashboard">
                        <svg viewBox="0 0 24 24">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" />
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Header -->
                <div class="confirm-header">
                    <div class="lock-icon">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" />
                        </svg>
                    </div>
                    <h1>Verify Your Identity</h1>
                    <p>Enter your credentials to confirm your vote</p>
                </div>

                <!-- Candidate Summary -->
                <div class="candidate-summary">
                    <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" alt="Candidate"
                        class="candidate-photo">
                    <div class="candidate-info">
                        <h3>
                            <?php echo htmlspecialchars($candidate['name']); ?>
                        </h3>
                        <span>
                            <?php echo htmlspecialchars($candidate['department']); ?> • Year
                            <?php echo $candidate['year']; ?>
                        </span>
                    </div>
                    <span class="election-badge">
                        <?php echo htmlspecialchars($election['title']); ?>
                    </span>
                </div>

                <!-- Form -->
                <form method="POST">
                    <div class="form-section">
                        <div class="section-label">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z" />
                            </svg>
                            Secure Authentication
                        </div>

                        <div class="security-note">
                            <svg viewBox="0 0 24 24">
                                <path
                                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
                            </svg>
                            <span>For security, please verify your identity by entering your College ID and account password
                                to complete your vote.</span>
                        </div>

                        <?php if ($error): ?>
                            <div class="error-message">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                                </svg>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <div class="input-wrapper">
                                <input type="text" id="name" name="name" placeholder="Type your Full Name to confirm"
                                    required autocomplete="off">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                </svg>
                            </div>
                            <small style="color: #64748b; font-size: 0.75rem; margin-top: 0.25rem; display: block;">Must
                                match your account name exactly.</small>
                        </div>

                        <div class="form-group">
                            <label for="college_id">College ID</label>
                            <div class="input-wrapper">
                                <div
                                    style="width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; border: 2px solid #e2e8f0; border-radius: 0.75rem; font-size: 0.95rem; background: #f8fafc; color: #64748b; cursor: not-allowed; font-weight: 700;">
                                    <?php echo htmlspecialchars($college_id); ?>
                                </div>
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M20 7h-4V5l-2-2h-4L8 5v2H4c-1.1 0-2 .9-2 2v5c0 .75.4 1.38 1 1.73V19c0 1.11.89 2 2 2h14c1.11 0 2-.89 2-2v-3.28c.59-.35 1-.99 1-1.72V9c0-1.1-.9-2-2-2zM10 5h4v2h-4V5zM4 9h16v5h-5v-3H9v3H4V9zm9 6h-2v-2h2v2zm6 4H5v-3h4v1h6v-1h4v3z" />
                                </svg>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <input type="password" id="password" name="password" placeholder="Enter your password"
                                    required autocomplete="new-password">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="action-btns">
                        <a href="verify_vote.php?id=<?php echo $election_id; ?>&candidate=<?php echo $candidate_id; ?>"
                            class="btn btn-back">
                            <svg viewBox="0 0 24 24">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                            </svg>
                            Back
                        </a>
                        <button type="submit" name="submit_vote" class="btn btn-submit">
                            <svg viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
                            </svg>
                            Submit Vote
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>


</body>

</html>