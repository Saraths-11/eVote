<?php
session_start();
include 'config.php';

// Access Control: Admin Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_elections.php");
    exit();
}

$id = intval($_GET['id']);
$error = '';
$success = '';

// Fetch existing details
$stmt = $conn->prepare("SELECT * FROM elections WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$election = $result->fetch_assoc();

if (!$election) {
    die("Election not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $eligible_voters = intval($_POST['eligible_voters'] ?? 0);
    $reg_start = $_POST['registration_start'];
    $reg_end = $_POST['registration_end'];
    $ele_start = $_POST['election_start'];
    $ele_end = $_POST['election_end'];
    $status = $_POST['status'];

    // Boundaries
    $now = time();
    $ts_reg_start = strtotime($reg_start);
    $ts_reg_end = strtotime($reg_end);
    $ts_ele_start = strtotime($ele_start);
    $ts_ele_end = strtotime($ele_end);

    // Strict Backend Validation
    if (empty($title) || empty($description) || empty($reg_start) || empty($reg_end) || empty($ele_start) || empty($ele_end) || $eligible_voters <= 0) {
        $error = "All fields are required, and eligible voters must be greater than 0.";
    } elseif (
        date('Y', $ts_reg_start) !== '2026' ||
        date('Y', $ts_reg_end) !== '2026' ||
        date('Y', $ts_ele_start) !== '2026' ||
        date('Y', $ts_ele_end) !== '2026'
    ) {
        $error = "Only dates within the year 2026 are allowed.";
    } elseif ($ts_reg_end <= $ts_reg_start) {
        $error = "Registration End Date must be after Registration Start Date.";
    } elseif ($ts_ele_start <= $ts_reg_end) {
        $error = "Election must start after Participant Registration ends.";
    } elseif ($ts_ele_end <= $ts_ele_start) {
        $error = "Election End Date must be after Election Start Date.";
    } elseif (strtotime(date('H:i', $ts_ele_start)) < strtotime('09:00') || strtotime(date('H:i', $ts_ele_start)) > strtotime('16:00')) {
        $error = "Voting start time must be between 09:00 AM and 04:00 PM.";
    } elseif (strtotime(date('H:i', $ts_ele_end)) < strtotime('09:00') || strtotime(date('H:i', $ts_ele_end)) > strtotime('16:00')) {
        $error = "Voting end time must be between 09:00 AM and 04:00 PM.";
    } elseif (strtotime(date('H:i', $ts_ele_end)) <= strtotime(date('H:i', $ts_ele_start))) {
        $error = "Voting end time must be after voting start time.";
    } else {
        // Update Database
        $update_stmt = $conn->prepare("UPDATE elections SET title=?, description=?, eligible_voters=?, registration_start=?, registration_end=?, election_start=?, election_end=?, status=? WHERE id=?");
        if ($update_stmt) {
            $update_stmt->bind_param("ssisssssi", $title, $description, $eligible_voters, $reg_start, $reg_end, $ele_start, $ele_end, $status, $id);
            if ($update_stmt->execute()) {
                $success = "Election updated successfully!";
                // Refresh local data
                $election['title'] = $title;
                $election['description'] = $description;
                $election['eligible_voters'] = $eligible_voters;
                $election['registration_start'] = $reg_start;
                $election['registration_end'] = $reg_end;
                $election['election_start'] = $ele_start;
                $election['election_end'] = $ele_end;
                $election['status'] = $status;
            } else {
                $error = "Database Error: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}

// Convert DB dates to HTML datetime-local format
function formatDT($dateStr)
{
    return date('Y-m-d\TH:i', strtotime($dateStr));
}

$current_dt = "2026-01-09T11:30";
$max_2026 = "2026-12-31T23:59";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Election | eVote Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --secondary: #F3F4F6;
            --accent: #F59E0B;
            --success: #10B981;
            --error: #EF4444;
            --bg: #F9FAFB;
            --text-main: #111827;
            --text-muted: #6B7280;
            --glass: rgba(255, 255, 255, 0.8);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            background-image: radial-gradient(circle at top right, rgba(79, 70, 229, 0.05), transparent),
                radial-gradient(circle at bottom left, rgba(245, 158, 11, 0.05), transparent);
        }

        .navbar {
            background: var(--glass);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        .form-header {
            margin-bottom: 2.5rem;
            text-align: center;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.01);
        }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--secondary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            font-size: 1rem;
            transition: all 0.2s;
            background: #FDFDFD;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease-out;
        }

        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FEE2E2;
        }

        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #D1FAE5;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text-main);
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .info-card {
            background: #EFF6FF;
            padding: 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            color: #1E40AF;
            margin-top: 0.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="nav-brand">eVote Admin</div>
        <div class="nav-actions">
            <a href="manage_elections.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Back</a>
        </div>
    </nav>

    <div class="container">
        <div class="form-header">
            <h1>Modify Election</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><span><?php echo htmlspecialchars($success); ?></span></div>
        <?php endif; ?>

        <form action="" method="POST" onsubmit="return validateForm()">
            <div class="card">
                <div class="form-section">
                    <div class="section-title"><span>🏷️</span> Status & Type</div>
                    <div class="form-group">
                        <label class="form-label">Current Status</label>
                        <select name="status" class="form-control">
                            <?php
                            $statuses = ['Upcoming', 'Registration Open', 'Registration Ended', 'Voting Active', 'Completed', 'Cancelled'];
                            foreach ($statuses as $s) {
                                $sel = ($election['status'] == $s) ? 'selected' : '';
                                echo "<option value='$s' $sel>$s</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><span>📝</span> Basic Info</div>
                    <div class="form-group">
                        <label class="form-label">Election Title</label>
                        <input type="text" name="title" class="form-control"
                            value="<?php echo htmlspecialchars($election['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control"
                            required><?php echo htmlspecialchars($election['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Eligible Voters</label>
                        <input type="number" name="eligible_voters" class="form-control"
                            value="<?php echo $election['eligible_voters']; ?>" required min="1">
                        <div class="info-card"
                            style="margin-top: 0.5rem; background: #FFFBEB; color: #92400E; border: 1px solid #FEF3C7;">
                            <span>📊</span>
                            <p>This number will be used to calculate vote percentages. (e.g., if 60 students are
                                eligible to vote).</p>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><span>👥</span> Registration Schedule</div>
                    <div class="row">
                        <div class="form-group">
                            <label class="form-label">Registration Starts</label>
                            <input type="datetime-local" name="registration_start" id="registration_start"
                                class="form-control" value="<?php echo formatDT($election['registration_start']); ?>"
                                min="2026-01-01T00:00" max="2026-12-31T23:59" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Registration Ends</label>
                            <input type="datetime-local" name="registration_end" id="registration_end"
                                class="form-control" value="<?php echo formatDT($election['registration_end']); ?>"
                                min="2026-01-01T00:00" max="2026-12-31T23:59" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><span>🗳️</span> Election Schedule</div>
                    <div class="row">
                        <div class="form-group">
                            <label class="form-label">Voting Starts</label>
                            <input type="datetime-local" name="election_start" id="election_start" class="form-control"
                                value="<?php echo formatDT($election['election_start']); ?>" min="2026-01-01T00:00"
                                max="2026-12-31T23:59" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Voting Ends</label>
                            <input type="datetime-local" name="election_end" id="election_end" class="form-control"
                                value="<?php echo formatDT($election['election_end']); ?>" min="2026-01-01T00:00"
                                max="2026-12-31T23:59" required>
                        </div>
                    </div>
                    <div class="info-card" style="background: #ECFDF5; color: #065F46; border: 1px solid #D1FAE5;">
                        <span>🕐</span>
                        <p><strong>Voting Time Range:</strong> Voting time must be set between <strong>09:00 AM</strong>
                            and <strong>04:00 PM</strong>. You can choose any time within this range.</p>
                    </div>
                </div>

                <div class="actions">
                    <a href="manage_elections.php" class="btn btn-secondary">Discard</a>
                    <button type="submit" class="btn btn-primary" style="margin-left: 1rem;">Update Election</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Helper function to extract time in minutes from datetime-local value
        function getTimeInMinutes(datetimeValue) {
            if (!datetimeValue) return null;
            const timePart = datetimeValue.split('T')[1];
            if (!timePart) return null;
            const [hours, minutes] = timePart.split(':').map(Number);
            return hours * 60 + minutes;
        }

        // Constants for allowed time range (09:00 AM = 540 minutes, 04:00 PM = 960 minutes)
        const MIN_VOTING_TIME = 9 * 60;  // 09:00 AM in minutes
        const MAX_VOTING_TIME = 16 * 60; // 04:00 PM in minutes

        function validateForm() {
            const regStart = new Date(document.getElementById('registration_start').value);
            const regEnd = new Date(document.getElementById('registration_end').value);
            const eleStartValue = document.getElementById('election_start').value;
            const eleEndValue = document.getElementById('election_end').value;
            const eleStart = new Date(eleStartValue);
            const eleEnd = new Date(eleEndValue);

            // Get time in minutes for validation
            const eleStartTimeMinutes = getTimeInMinutes(eleStartValue);
            const eleEndTimeMinutes = getTimeInMinutes(eleEndValue);

            // 0. Voting time range check (09:00 AM to 04:00 PM)
            if (eleStartTimeMinutes !== null && (eleStartTimeMinutes < MIN_VOTING_TIME || eleStartTimeMinutes > MAX_VOTING_TIME)) {
                alert("Voting start time must be between 09:00 AM and 04:00 PM.");
                return false;
            }
            if (eleEndTimeMinutes !== null && (eleEndTimeMinutes < MIN_VOTING_TIME || eleEndTimeMinutes > MAX_VOTING_TIME)) {
                alert("Voting end time must be between 09:00 AM and 04:00 PM.");
                return false;
            }
            // Check that end time is after start time (within the same day context)
            if (eleStartTimeMinutes !== null && eleEndTimeMinutes !== null && eleEndTimeMinutes <= eleStartTimeMinutes) {
                alert("Voting end time must be after voting start time.");
                return false;
            }

            // Year 2026 check
            const allDates = [regStart, regEnd, eleStart, eleEnd];
            for (let date of allDates) {
                if (date.getFullYear() !== 2026) {
                    alert("Only dates within the year 2026 are allowed.");
                    return false;
                }
            }

            if (regEnd <= regStart) { alert("Registration end must be after start."); return false; }
            if (eleStart <= regEnd) { alert("Election must start after registration ends."); return false; }
            if (eleEnd <= eleStart) { alert("Election end must be after start."); return false; }
            return true;
        }
    </script>
</body>

</html>