<?php
session_start();
include 'config.php';

// Authentication Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: student_dashboard.php");
    exit();
}

$election_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

// 1. Fetch Election and Participant Details
$stmt = $conn->prepare("SELECT e.*, p.id as participant_id, p.status as participant_status 
                       FROM elections e 
                       JOIN participants p ON e.id = p.election_id 
                       WHERE e.id = ? AND p.user_id = ?");
$stmt->bind_param("ii", $election_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("You are not a participant in this election.");
}

// 2. Validate Cancellation Period (Admin Controlled)
if ($data['participant_status'] !== 'Approved') {
    die("Only approved candidates can cancel their nomination.");
}

if ($data['nomination_status'] !== 'open') {
    die("Nomination cancellation period is not currently open.");
}

$error = '';
$success = '';

// 3. Handle Cancellation Posting with Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    $college_id = $_POST['college_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // Verify Credentials
    $verify_stmt = $conn->prepare("SELECT password, college_id FROM users WHERE id = ?");
    $verify_stmt->bind_param("i", $user_id);
    $verify_stmt->execute();
    $user_data = $verify_stmt->get_result()->fetch_assoc();

    if ($college_id !== $user_data['college_id']) {
        $error = "Verification Failed: Incorrect College ID.";
    } elseif (!password_verify($password, $user_data['password'])) {
        $error = "Verification Failed: Incorrect Password.";
    } else {
        // Proceed with Cancellation
        $update_stmt = $conn->prepare("UPDATE participants SET status = 'Cancelled', cancellation_reason = ?, cancelled_at = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $reason, $now, $data['participant_id']);

        if ($update_stmt->execute()) {
            header("Location: student_dashboard.php?msg=cancelled");
            exit();
        } else {
            $error = "Error updating status: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Cancellation | eVote</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 2rem 0;
        }

        .card {
            background: white;
            padding: 3rem;
            border-radius: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            max-width: 550px;
            width: 90%;
            text-align: center;
            border: 1px solid #f1f5f9;
        }

        .icon-box {
            width: 80px;
            height: 80px;
            background: #fef2f2;
            color: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }

        h1 {
            color: #0f172a;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        p {
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .warning-box {
            background: #fff7ed;
            border: 1px solid #ffedd5;
            padding: 1.5rem;
            border-radius: 1.25rem;
            color: #9a3412;
            font-size: 0.9rem;
            text-align: left;
            margin-bottom: 2rem;
        }

        .form-group {
            text-align: left;
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .btn-cancel {
            background: #ef4444;
            color: white;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 800;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
            font-size: 1rem;
            margin-top: 1rem;
        }

        .btn-cancel:hover {
            background: #dc2626;
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 15px 30px -5px rgba(239, 68, 68, 0.3);
        }


        .btn-back {
            display: block;
            margin-top: 1.5rem;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: color 0.2s;
        }

        .btn-back:hover {
            color: #1e293b;
        }

        .error-alert {
            background: #fef2f2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid #fee2e2;
        }

        textarea.form-control {
            resize: none;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon-box">🛡️</div>
        <h1>Identity Verification</h1>
        <p>Please verify your identity to cancel your nomination
            for<br><strong><?php echo htmlspecialchars($data['title']); ?></strong>.</p>


        <?php if ($error): ?>
            <div class="error-alert">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="warning-box">
            <strong>⚠️ Irreversible Action:</strong>
            <ul style="margin-top: 0.5rem; padding-left: 1.25rem;">
                <li>You will be removed from the active candidate list.</li>
                <li>This action cannot be undone by you or the admin.</li>
            </ul>
        </div>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="college_id">Confirm College ID</label>
                <input type="text" id="college_id" name="college_id" class="form-control" placeholder="e.g. 20261001"
                    required>
            </div>

            <div class="form-group">
                <label for="password">Account Password</label>
                <input type="password" id="password" name="password" class="form-control"
                    placeholder="Enter your password" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="reason">Reason for Cancellation (Optional)</label>
                <textarea id="reason" name="reason" class="form-control" rows="3"
                    placeholder="Tell us why you are withdrawing..."></textarea>
            </div>

            <button type="submit" name="confirm_cancel" class="btn-cancel">Verify & Confirm Cancellation</button>
            <a href="student_dashboard.php" class="btn-back">Cancel & Go Back</a>
        </form>
    </div>
</body>

</html>