<?php
session_start();
include 'config.php';

// Access Control: Admin Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';

// singleton check
$check_active = $conn->query("SELECT id FROM elections WHERE status != 'Closed' LIMIT 1");
$existing_election = $check_active->fetch_assoc();
$has_active_election = ($existing_election !== null);

// Check for existing elections to prevent overlaps
$check_latest = $conn->query("SELECT MAX(election_end) as latest_end FROM elections");
$latest_row = $check_latest->fetch_assoc();
$latest_election_end = $latest_row['latest_end'] ? strtotime($latest_row['latest_end']) : 0;

// JavaScript friendly min-date ( must be after latest election end OR current time )
$baseline_time = max(time(), $latest_election_end);
$min_dt = date('Y-m-d\TH:i', $baseline_time + 60); // add 1 minute cushion

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    // $eligible_voters = intval($_POST['eligible_voters'] ?? 0); // Field removed
    $eligible_voters = 0;
    $reg_start = $_POST['registration_start'];
    $reg_end = $_POST['registration_end'];
    $can_start = $_POST['cancellation_start'];
    $can_end = $_POST['cancellation_end'];
    $ele_start = $_POST['election_start'];
    $ele_end = $_POST['election_end'];

    // Current time and boundaries
    $now = time();
    $year_2026_start = strtotime("2026-01-01 00:00:00");
    $year_2026_end = strtotime("2026-12-31 23:59:59");

    $ts_reg_start = strtotime($reg_start);
    $ts_reg_end = strtotime($reg_end);
    $ts_can_start = strtotime($can_start);
    $ts_can_end = strtotime($can_end);
    $ts_ele_start = strtotime($ele_start);
    $ts_ele_end = strtotime($ele_end);

    // Strict Backend Validation
    if ($has_active_election) {
        $error = "A new election cannot be created because there is already an election in progress or scheduled. You must explicitly close the current election first.";
    } elseif (empty($title) || empty($description) || empty($reg_start) || empty($reg_end) || empty($can_start) || empty($can_end) || empty($ele_start) || empty($ele_end)) {
        $error = "All fields are required.";
    } elseif ($ts_reg_start <= $latest_election_end) {
        $error = "New election dates must be strictly AFTER the previous election's end date (" . date('M d, Y H:i', $latest_election_end) . ").";
    } elseif ($ts_reg_start < $now || $ts_reg_end < $now || $ts_can_start < $now || $ts_can_end < $now || $ts_ele_start < $now || $ts_ele_end < $now) {
        $error = "Dates cannot be in the past.";
    } elseif (
        date('Y', $ts_reg_start) !== '2026' ||
        date('Y', $ts_reg_end) !== '2026' ||
        date('Y', $ts_can_start) !== '2026' ||
        date('Y', $ts_can_end) !== '2026' ||
        date('Y', $ts_ele_start) !== '2026' ||
        date('Y', $ts_ele_end) !== '2026'
    ) {
        $error = "Only dates within the year 2026 are allowed.";
    } elseif ($ts_reg_end <= $ts_reg_start) {
        $error = "Registration End Date must be after Registration Start Date.";
    } elseif ($ts_can_start <= $ts_reg_end) {
        $error = "Nomination Cancellation period must start after Registration End Date.";
    } elseif ($ts_can_end <= $ts_can_start) {
        $error = "Nomination Cancellation End Date must be after Cancellation Start Date.";
    } elseif ($ts_ele_start <= $ts_can_end) {
        $error = "Election must start after Nomination Cancellation period ends.";
    } elseif ($ts_ele_end <= $ts_ele_start) {
        $error = "Election End Date must be after Election Start Date.";
    } elseif (strtotime(date('H:i', $ts_ele_start)) < strtotime('09:00') || strtotime(date('H:i', $ts_ele_start)) > strtotime('16:00')) {
        $error = "Voting start time must be between 09:00 AM and 04:00 PM.";
    } elseif (strtotime(date('H:i', $ts_ele_end)) < strtotime('09:00') || strtotime(date('H:i', $ts_ele_end)) > strtotime('16:00')) {
        $error = "Voting end time must be between 09:00 AM and 04:00 PM.";
    } elseif (strtotime(date('H:i', $ts_ele_end)) <= strtotime(date('H:i', $ts_ele_start))) {
        $error = "Voting end time must be after voting start time.";
    } elseif (date('Y-m-d', $ts_ele_start) !== date('Y-m-d', $ts_ele_end)) {
        $error = "Election start and end must be on the same day.";
    } elseif (date('w', $ts_ele_start) == 0 || (date('w', $ts_ele_start) == 6 && date('j', $ts_ele_start) > 7 && date('j', $ts_ele_start) <= 14)) {
        $error = "Election dates cannot be scheduled on Sundays or Second Saturdays. Please choose another date.";
    } elseif (date('w', $ts_ele_end) == 0 || (date('w', $ts_ele_end) == 6 && date('j', $ts_ele_end) > 7 && date('j', $ts_ele_end) <= 14)) {
        $error = "Election dates cannot be scheduled on Sundays or Second Saturdays. Please choose another date.";
    } else {
        $show_voters = 0;
        $status = 'active';
        $is_published = 0;

        // Insert into Database
        $stmt = $conn->prepare("INSERT INTO elections (title, description, eligible_voters, registration_start, registration_end, nomination_cancellation_start, nomination_cancellation_end, election_start, election_end, status, registration_status, voting_status, registration_ended, nomination_ended, voting_ended, createdBy, visibleTo, is_published, show_voters) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'closed', 'not_started', 0, 0, 0, 'admin', 'students', ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssisssssssii", $title, $description, $eligible_voters, $reg_start, $reg_end, $can_start, $can_end, $ele_start, $ele_end, $status, $is_published, $show_voters);
            if ($stmt->execute()) {
                header("Location: manage_elections.php?msg=created");
                exit();
            } else {
                $error = "Database Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}

// Get current date-time for min attribute in JS-friendly format
$current_dt = $min_dt;
$max_2026 = "2026-12-31T23:59";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Election | eVote Admin</title>
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

        .form-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
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
        }

        .section-title i {
            color: var(--primary);
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
            background: white;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
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
            justify-content: center;
            gap: 0.5rem;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text-main);
        }

        .btn-secondary:hover {
            background: #E5E7EB;
            transform: translateY(-1px);
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
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

        @media (max-width: 640px) {
            .row {
                grid-template-columns: 1fr;
            }

            .container {
                margin: 1.5rem auto;
            }

            .card {
                padding: 1.5rem;
            }

            .form-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="nav-brand">eVote Admin</div>
        <div class="nav-actions">
            <a href="admin_dashboard.php" class="btn btn-secondary" style="padding: 0.50rem 1rem;">Dashboard</a>
            <a href="logout.php" class="btn btn-secondary"
                style="padding: 0.50rem 1rem; margin-left: 0.5rem; color: var(--error);">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="form-header">
            <h1>Create Election</h1>
            <p>Configure the scheduling and details for a new online election.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd"></path>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($has_active_election): ?>
            <div class="card" style="border-left: 6px solid var(--error);">
                <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.5rem;">
                    <div style="font-size: 3.5rem;">⚠️</div>
                    <h2 style="color: #991B1B;">Cannot Create New Election</h2>
                    <p style="color: #4B5563; line-height: 1.6; max-width: 600px;">
                        A new election cannot be created because an election is already in progress or scheduled. 
                        Our system prevents multiple elections to maintain integrity during:
                    </p>
                    <ul style="color: #4B5563; text-align: left; margin: 0.5rem 0; font-weight: 500; font-size: 0.95rem; list-style-type: '👉 '; padding-left: 1.5rem;">
                        <li>Participant Registration Period</li>
                        <li>Nomination Cancellation Period</li>
                        <li>Election (Voting) Period</li>
                    </ul>
                    <div
                        style="background: #FEF2F2; padding: 1.25rem 1.5rem; border-radius: 12px; border: 1px solid #FEE2E2; font-weight: 700; color: #B91C1C; margin-top: 1rem;">
                        You must officially CLOSE the current election before starting a new one.
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                        <a href="manage_elections.php" class="btn btn-primary">Manage Current Election</a>
                        <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form action="" method="POST" id="electionForm" onsubmit="return validateForm()">
                <div class="card">
                    <!-- Step 1: Basic Info -->
                    <div class="form-section">
                        <div class="section-title">
                            <span>📝</span> General Information
                        </div>
                        <div class="form-group">
                            <label class="form-label">Election Title</label>
                            <input type="text" name="title" class="form-control"
                                placeholder="e.g., Student Union Election 2026" required autofocus>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"
                                placeholder="Explain the purpose, eligibility, and rules..." required></textarea>
                        </div>
                    </div>

                    <!-- Step 2: Registration Schedule -->
                    <div class="form-section">
                        <div class="section-title">
                            <span>👥</span> Participate Registration Start & End Date
                        </div>
                        <div class="row">
                            <div class="form-group">
                                <label class="form-label">Registration Starts</label>
                                <input type="datetime-local" name="registration_start" id="registration_start"
                                    class="form-control" min="<?php echo $current_dt; ?>" max="<?php echo $max_2026; ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="form-label : registration_end">Registration Ends</label>
                                <input type="datetime-local" name="registration_end" id="registration_end"
                                    class="form-control" min="<?php echo $current_dt; ?>" max="<?php echo $max_2026; ?>"
                                    required>
                            </div>
                        </div>
                        <div class="info-card">
                            <span>ℹ️</span>
                            <p>Students can only register as participants during this period. Registration must end before
                                the election starts.</p>
                        </div>
                    </div>

                    <!-- Step 3: Nomination Cancellation Schedule -->
                    <div class="form-section">
                        <div class="section-title">
                            <span>🔓</span> Nomination Cancellation Period
                        </div>
                        <div class="row">
                            <div class="form-group">
                                <label class="form-label">Cancellation Starts</label>
                                <input type="datetime-local" name="cancellation_start" id="cancellation_start"
                                    class="form-control" min="<?php echo $current_dt; ?>" max="<?php echo $max_2026; ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Cancellation Ends</label>
                                <input type="datetime-local" name="cancellation_end" id="cancellation_end"
                                    class="form-control" min="<?php echo $current_dt; ?>" max="<?php echo $max_2026; ?>"
                                    required>
                            </div>
                        </div>
                        <div class="info-card" style="background: #FFFBEB; color: #92400E; border: 1px solid #FEF3C7;">
                            <span>ℹ️</span>
                            <p>Approved candidates can only cancel their nomination during this period. This must be AFTER
                                registration ends and BEFORE the election starts.</p>
                        </div>
                    </div>

                    <!-- Step 4: Election Schedule -->
                    <div class="form-section">
                        <div class="section-title">
                            <span>🗳️</span> Election Start & End Date
                        </div>
                        <div class="row">
                            <div class="form-group">
                                <label class="form-label">Voting Starts</label>
                                <input type="datetime-local" name="election_start" id="election_start" class="form-control"
                                    min="<?php echo $current_dt; ?>" max="<?php echo $max_2026; ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Voting Ends</label>
                                <input type="datetime-local" name="election_end" id="election_end" class="form-control"
                                    min="<?php echo $current_dt; ?>" max="<?php echo $max_2026; ?>" required>
                            </div>
                        </div>
                        <div class="info-card" style="background: #ECFDF5; color: #065F46; border: 1px solid #D1FAE5;">
                            <span>🕐</span>
                            <p><strong>Voting Time Range:</strong> Voting time must be set between <strong>09:00 AM</strong>
                                and <strong>04:00 PM</strong>. You can choose any time within this range.</p>
                        </div>
                        <div class="actions">
                            <a href="manage_elections.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Election</button>
                        </div>
                    </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Set dynamic min/max for date inputs
        const currentYear = 2026;
        const now = new Date("2026-01-09T11:30:00");

        function formatDateForInput(date) {
            return date.toISOString().slice(0, 16);
        }

        const inputs = ['registration_start', 'registration_end', 'cancellation_start', 'cancellation_end', 'election_start', 'election_end'];

        // Add real-time validation to improve UX
        document.getElementById('registration_start').addEventListener('change', function () {
            document.getElementById('registration_end').min = this.value;
        });

        document.getElementById('registration_end').addEventListener('change', function () {
            document.getElementById('cancellation_start').min = this.value;
        });

        document.getElementById('cancellation_start').addEventListener('change', function () {
            document.getElementById('cancellation_end').min = this.value;
        });

        document.getElementById('cancellation_end').addEventListener('change', function () {
            document.getElementById('election_start').min = this.value;
        });

        document.getElementById('election_start').addEventListener('change', function () {
            document.getElementById('election_end').min = this.value;
        });

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

        // Real-time validation for election time fields
        function validateVotingTime(inputElement, fieldName) {
            const timeInMinutes = getTimeInMinutes(inputElement.value);
            const errorId = inputElement.id + '_error';
            let errorElement = document.getElementById(errorId);

            // Create error element if it doesn't exist
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.id = errorId;
                errorElement.style.cssText = 'color: #DC2626; font-size: 0.85rem; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;';
                inputElement.parentNode.appendChild(errorElement);
            }

            if (timeInMinutes !== null && (timeInMinutes < MIN_VOTING_TIME || timeInMinutes > MAX_VOTING_TIME)) {
                errorElement.innerHTML = '⚠️ ' + fieldName + ' must be between 09:00 AM and 04:00 PM';
                inputElement.style.borderColor = '#DC2626';
                return false;
            } else {
                errorElement.innerHTML = '';
                inputElement.style.borderColor = '';
                return true;
            }
        }

        function isInvalidDay(dateObj) {
            const day = dateObj.getDay();
            const dateObjDate = dateObj.getDate();
            if (day === 0) return true; // Sunday
            if (day === 6 && dateObjDate > 7 && dateObjDate <= 14) return true; // Second Saturday
            return false;
        }

        function displayDateError(inputElement, errorMessage) {
            const errorId = inputElement.id + '_date_error';
            let errorElement = document.getElementById(errorId);

            if (!errorElement && errorMessage) {
                errorElement = document.createElement('div');
                errorElement.id = errorId;
                errorElement.style.cssText = 'color: #DC2626; font-size: 0.85rem; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;';
                inputElement.parentNode.appendChild(errorElement);
            }

            if (errorElement) {
                errorElement.innerHTML = errorMessage;
            }

            // Sync border color with time validation error if any
            const timeErrorElement = document.getElementById(inputElement.id + '_error');
            const hasTimeError = timeErrorElement && timeErrorElement.innerHTML !== '';

            if (errorMessage || hasTimeError) {
                inputElement.style.borderColor = '#DC2626';
            } else {
                inputElement.style.borderColor = '';
            }
        }

        function validateRealTimeElectionDates() {
            const eleStartInput = document.getElementById('election_start');
            const eleEndInput = document.getElementById('election_end');
            const eleStartValue = eleStartInput.value;
            const eleEndValue = eleEndInput.value;

            let startError = '';
            let endError = '';
            let isValid = true;

            if (eleStartValue) {
                const eleStart = new Date(eleStartValue);
                if (isInvalidDay(eleStart)) {
                    startError = '⚠️ Election dates cannot be scheduled on Sundays or Second Saturdays.';
                    isValid = false;
                }
            }

            if (eleEndValue) {
                const eleEnd = new Date(eleEndValue);
                if (isInvalidDay(eleEnd)) {
                    endError = '⚠️ Election dates cannot be scheduled on Sundays or Second Saturdays.';
                    isValid = false;
                }
            }

            if (eleStartValue && eleEndValue) {
                const eleStartStr = eleStartValue.split('T')[0];
                const eleEndStr = eleEndValue.split('T')[0];
                if (eleStartStr !== eleEndStr) {
                    const sameDayError = '⚠️ Election start and end must be on the same day.';
                    startError = startError || sameDayError;
                    endError = endError || sameDayError;
                    isValid = false;
                }
            }

            displayDateError(eleStartInput, startError);
            displayDateError(eleEndInput, endError);
            return isValid;
        }

        // Add real-time validation to election time inputs
        document.getElementById('election_start').addEventListener('input', function () {
            validateVotingTime(this, 'Voting start time');
            validateRealTimeElectionDates();
        });

        document.getElementById('election_end').addEventListener('input', function () {
            validateVotingTime(this, 'Voting end time');
            validateRealTimeElectionDates();
        });

        function validateForm() {
            const regStart = new Date(document.getElementById('registration_start').value);
            const regEnd = new Date(document.getElementById('registration_end').value);
            const canStart = new Date(document.getElementById('cancellation_start').value);
            const canEnd = new Date(document.getElementById('cancellation_end').value);
            const eleStartValue = document.getElementById('election_start').value;
            const eleEndValue = document.getElementById('election_end').value;
            const eleStart = new Date(eleStartValue);
            const eleEnd = new Date(eleEndValue);
            const currentTime = new Date();

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

            if (!validateRealTimeElectionDates()) {
                return false;
            }


            // 1. Past dates check
            if (regStart < currentTime || regEnd < currentTime || canStart < currentTime || canEnd < currentTime || eleStart < currentTime || eleEnd < currentTime) {
                alert("Only future dates and times are allowed.");
                return false;
            }

            // 2. Year 2026 check
            const allDates = [regStart, regEnd, canStart, canEnd, eleStart, eleEnd];
            for (let date of allDates) {
                if (date.getFullYear() !== 2026) {
                    alert("Only dates within the year 2026 are allowed.");
                    return false;
                }
            }

            if (regEnd <= regStart) {
                alert("Participant registration end date must be after registration start date.");
                return false;
            }

            if (canStart <= regEnd) {
                alert("Nomination cancellation period must start after registration has ended.");
                return false;
            }

            if (canEnd <= canStart) {
                alert("Nomination cancellation end date must be after cancellation start date.");
                return false;
            }

            if (eleStart <= canEnd) {
                alert("The election can only start after nomination cancellation period has ended.");
                return false;
            }

            if (eleEnd <= eleStart) {
                alert("Election end date must be after the election start date.");
                return false;
            }

            return true;
        }
    </script>
</body>

</html>