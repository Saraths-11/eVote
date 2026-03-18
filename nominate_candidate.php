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

// 1. Fetch Election and Participant Details
$stmt = $conn->prepare("SELECT e.*, p.id as participant_id, p.status as participant_status, p.position 
                       FROM elections e 
                       JOIN participants p ON e.id = p.election_id 
                       WHERE e.id = ? AND p.user_id = ?");
$stmt->bind_param("ii", $election_id, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Data not found: You might not be a registered participant for this election.");
}

// 2. Validate Nomination Phase
if ($data['participant_status'] !== 'Approved') {
    die("Access Denied: Only approved participants can submit nominations.");
}

if ($data['nomination_status'] !== 'open') {
    die("Nomination period is not currently active.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_nomination'])) {
    $position = trim($_POST['position'] ?? '');
    
    if (empty($position)) {
        $error = "Please specify the position you are contesting for.";
    } else {
        $update_stmt = $conn->prepare("UPDATE participants SET position = ? WHERE id = ?");
        $update_stmt->bind_param("si", $position, $data['participant_id']);
        
        if ($update_stmt->execute()) {
            $success = "Nomination submitted successfully!";
            $data['position'] = $position; // Update local data for display
        } else {
            $error = "Error updating nomination: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Nomination | eVote</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --bg: #F8FAFC;
            --text-main: #0F172A;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 2rem;
        }

        .card {
            background: white;
            padding: 3rem;
            border-radius: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            max-width: 550px;
            width: 100%;
            border: 1px solid #F1F5F9;
        }

        .icon-box {
            width: 64px;
            height: 64px;
            background: #EEF2FF;
            color: var(--primary);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        h1 {
            color: var(--text-main);
            font-size: 1.875rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        p.subtitle {
            color: #64748B;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 1.5rem;
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
            padding: 0.875rem 1.25rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 1rem;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s;
            box-sizing: border-box;
            background: #F9FAFB;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .btn-submit {
            background: var(--primary);
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

        .btn-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.2);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #D1FAE5; }
        .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FEE2E2; }

        .btn-back {
            display: block;
            margin-top: 1.5rem;
            text-align: center;
            color: #64748B;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: color 0.2s;
        }

        .btn-back:hover { color: var(--text-main); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-box">🗳️</div>
        <h1>Candidate Nomination</h1>
        <p class="subtitle">Assign yourself to a contesting position for:<br><strong><?php echo htmlspecialchars($data['title']); ?></strong></p>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="position">Contesting Position</label>
                <input type="text" id="position" name="position" class="form-control" 
                       placeholder="e.g. President, Secretary, General Secretary..." 
                       value="<?php echo htmlspecialchars($data['position'] ?? ''); ?>" required>
            </div>

            <button type="submit" name="submit_nomination" class="btn-submit">
                <?php echo empty($data['position']) ? 'Submit Nomination' : 'Update Nomination'; ?>
            </button>
            <a href="student_dashboard.php" class="btn-back">Cancel & Go Back</a>
        </form>
    </div>
</body>
</html>
