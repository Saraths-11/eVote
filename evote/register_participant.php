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

// Fetch user details from DB to implement strict identity binding
$u_stmt = $conn->prepare("SELECT accountFullName, email, college_id, department, year FROM users WHERE id = ?");
if ($u_stmt) {
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result();
    if ($u_row = $u_res->fetch_assoc()) {
        $db_name = trim($u_row['accountFullName']);
        $db_college_id = trim($u_row['college_id']);
        $db_dept = trim($u_row['department']);
        $db_year = trim($u_row['year']);
    }
    $u_stmt->close();
}

// Strict Identity Enforcements: Details are fetched from DB and locked
$name = $db_name ?? '';
$college_id = $db_college_id ?? '';
$dept = $db_dept ?? '';
$year = $db_year ?? '';

// Fetch Election Details
$stmt = $conn->prepare("SELECT * FROM elections WHERE id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

if (!$election) {
    die("Election not found.");
}

// Check if already registered
$check_stmt = $conn->prepare("SELECT status, cancellation_reason, proof_path, signature_path FROM participants WHERE election_id = ? AND user_id = ?");
$existing = null;
if ($check_stmt) {
    $check_stmt->bind_param("ii", $election_id, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
} else {
    $error = "System configuration error: Participation table not found. Please contact admin.";
}

$now = time();
$reg_start = strtotime($election['registration_start']);
$reg_end = strtotime($election['registration_end']);

// Determine if registration is open based on status
$is_reg_open = ($election['registration_status'] === 'open');
$reg_not_started = ($election['registration_status'] === 'closed' && time() < strtotime($election['registration_start']));
$reg_ended = ($election['registration_status'] === 'closed' && time() >= strtotime($election['registration_end']));

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_reg_open && !($existing && $existing['status'] !== null)) {
    // MANUAL IDENTITY INPUT HANDLING (Step 426 Global Validation Enforcement)
    $entered_name = trim($_POST['name'] ?? '');
    $entered_college_id = trim($_POST['college_id'] ?? '');
    $entered_dept = trim($_POST['department'] ?? '');
    $entered_year = trim($_POST['year'] ?? '');

    // Get other elective details from POST
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $rules_accepted = isset($_POST['rules_accepted']);

    // GLOBAL ACCOUNT-BOUND IDENTITY VALIDATION
    if (!$rules_accepted) {
        $error = "Please read and agree to the participant rules before submitting.";
    } elseif (empty($entered_name) || empty($entered_college_id) || empty($entered_dept) || empty($entered_year)) {
        $error = "All identity fields are required and must match your account.";
    } elseif (strcasecmp($entered_name, $db_name) !== 0 || 
              $entered_college_id !== $db_college_id || 
              strcasecmp($entered_dept, $db_dept) !== 0 || 
              $entered_year !== $db_year) {
        
        // Log mismatch as identity violation for security review
        $log_stmt = $conn->prepare("INSERT INTO voting_logs (election_id, user_id, action, status, ip_address, details) VALUES (?, ?, 'identity_violation', 'failed', ?, ?)");
        if ($log_stmt) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $details = "Mismatch in registration: NAME(" . ($entered_name === $db_name ? 'OK' : 'FAIL') . ") " .
                       "CID(" . ($entered_college_id === $db_college_id ? 'OK' : 'FAIL') . ") " .
                       "DEPT(" . (strcasecmp($entered_dept, $db_dept) == 0 ? 'OK' : 'FAIL') . ") " .
                       "YEAR(" . ($entered_year === $db_year ? 'OK' : 'FAIL') . ")";
            $log_stmt->bind_param("iiss", $election_id, $user_id, $ip, $details);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        $error = "Entered identity details do not match your account profile. Please enter your valid account information.";
    } elseif (empty($gender) || empty($dob)) {
        $error = "Gender and Date of Birth are required.";
    } elseif (empty($gender) || empty($dob)) {
        $error = "Gender and Date of Birth are required.";
    } elseif (empty($gender) || empty($dob)) {
        $error = "Gender and Date of Birth are required.";
    } elseif (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $error = "A passport-size photo is required.";
    } elseif (!isset($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "A scan of your ID proof is required.";
    } elseif (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
        $error = "A photo of your signature is required.";
    } else {
        // File Uploads
        $target_dir = "uploads/";
        if (!is_dir($target_dir))
            mkdir($target_dir, 0777, true);

        $photo_ext = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $proof_ext = pathinfo($_FILES["proof_file"]["name"], PATHINFO_EXTENSION);
        $sig_ext = pathinfo($_FILES["signature"]["name"], PATHINFO_EXTENSION);

        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array(strtolower($photo_ext), $allowed) || !in_array(strtolower($proof_ext), $allowed) || !in_array(strtolower($sig_ext), $allowed)) {
            $error = "Only JPG, JPEG & PNG files are allowed for all uploads.";
        } else {
            $photo_path = $target_dir . "photo_" . time() . "_" . $user_id . "." . $photo_ext;
            $proof_path = $target_dir . "proof_" . time() . "_" . $user_id . "." . $proof_ext;
            $sig_path = $target_dir . "sig_" . time() . "_" . $user_id . "." . $sig_ext;

            if (
                move_uploaded_file($_FILES["photo"]["tmp_name"], $photo_path) &&
                move_uploaded_file($_FILES["proof_file"]["tmp_name"], $proof_path) &&
                move_uploaded_file($_FILES["signature"]["tmp_name"], $sig_path)
            ) {

                $ins = $conn->prepare("INSERT INTO participants (election_id, user_id, name, college_id, gender, dob, department, year, photo_path, proof_path, signature_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("iisssssssss", $election_id, $user_id, $db_name, $db_college_id, $gender, $dob, $db_dept, $db_year, $photo_path, $proof_path, $sig_path);

                if ($ins->execute()) {
                    $success = "Registration submitted for verification.";
                    $existing = ['status' => 'Pending', 'proof_path' => $proof_path, 'signature_path' => $sig_path];
                } else {
                    $error = "Submission failed: " . $conn->error;
                }
            } else {
                $error = "Error uploading files.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participant Registration - eVote</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .reg-container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 2rem;
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.1),
                rgba(255, 255, 255, 0.4) 0px 0px 0px 1px inset;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .rules-box {
            background: #fefce8;
            border: 1px solid #fef08a;
            border-left: 5px solid #f59e0b;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 1rem;
        }

        .rules-box h4 {
            color: #854d0e;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rules-list {
            padding-left: 1.25rem;
            color: #713f12;
            font-size: 0.95rem;
        }

        .rules-list li {
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }

        .confirmation-box {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .confirmation-box:hover {
            border-color: var(--primary);
            background: #f1f5f9;
        }

        .confirmation-box input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
            margin-top: 0.2rem;
            cursor: pointer;
        }

        .confirmation-text {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--dark);
            user-select: none;
        }

        .form-section {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control.is-invalid {
            border-color: #dc2626 !important;
            background-color: #fff1f2;
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1) !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .status-banner {
            text-align: center;
            padding: 2.5rem;
            background: #f0fdf4;
            border-radius: 1.5rem;
            color: #166534;
            border: 1px solid #dcfce7;
        }

        .disabled-msg {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        /* Identity Field Lock Style */
        .identity-display-field {
            font-weight: 700;
            color: #1e293b;
            font-size: 1rem;
            padding: 0.85rem 1rem;
            background: #f1f5f9;
            border-radius: 0.5rem;
            border: 2px dashed #cbd5e1;
            cursor: not-allowed;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            position: relative;
        }

        .identity-display-field::after {
            content: '🔒 LOCKED';
            position: absolute;
            right: 1rem;
            font-size: 0.65rem;
            background: #cbd5e1;
            color: #475569;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .identity-display-field {
            font-weight: 700;
            color: #1e293b;
            font-size: 1rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            cursor: not-allowed;
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Participant</div>
        <div class="nav-menu">
            <a href="enroll.php?id=<?php echo $election_id; ?>" class="btn btn-secondary">Back</a>
        </div>
    </nav>

    <div class="container">
        <div class="reg-container">
            <h2>Register as Participant</h2>
            <p style="color: #666; margin-bottom: 2rem;">
                <?php echo htmlspecialchars($election['title']); ?>
            </p>

            <?php if ($existing): ?>
                <div class="status-banner">
                    <h3>Status:
                        <?php echo $existing['status']; ?>
                    </h3>
                    <p>
                        <?php
                        if ($existing['status'] === 'Pending')
                            echo 'Your registration is under review.';
                        elseif ($existing['status'] === 'Cancelled')
                            echo '<span style="color:#dc2626; font-weight:600;">Your participation has been cancelled by the admin due to disciplinary action.</span><br><small>Reason: ' . htmlspecialchars($existing['cancellation_reason']) . '</small>';
                        else
                            echo 'Your registration has been ' . strtolower($existing['status']) . '.';
                        ?>
                    </p>
                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center;">
                        <a href="#" onclick="viewImg('<?php echo $existing['proof_path']; ?>'); return false;"
                            style="color: var(--primary); font-weight: 600; text-decoration: none; font-size: 0.9rem;">View
                            ID Proof</a>
                        <a href="#" onclick="viewImg('<?php echo $existing['signature_path']; ?>'); return false;"
                            style="color: var(--primary); font-weight: 600; text-decoration: none; font-size: 0.9rem;">View
                            Signature</a>
                    </div>
                    <a href="enroll.php?id=<?php echo $election_id; ?>" class="btn btn-primary"
                        style="margin-top: 1.5rem;">Back to Details</a>
                </div>
            <?php elseif (!$is_reg_open): ?>
                <div class="disabled-msg"
                    style="background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 2.5rem; border-radius: 1.5rem; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚫</div>
                    <h3 style="margin-bottom: 0.5rem;">Registration Not Available</h3>
                    <p style="margin-bottom: 1rem;">
                        <?php
                        if ($reg_not_started) {
                            echo "Registration is scheduled to start on <strong>" . date('M d, Y h:i A', $reg_start) . "</strong>.";
                        } elseif ($reg_ended) {
                            echo "The registration period for this election ended on <strong>" . date('M d, Y h:i A', $reg_end) . "</strong>.";
                        }
                        ?>
                    </p>
                    <a href="enroll.php?id=<?php echo $election_id; ?>" class="btn btn-secondary">Back to Details</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div class="rules-box">
                    <h4><span>📜</span> Participant Eligibility Rules</h4>
                    <ol class="rules-list">
                        <li>The participant must be a currently enrolled student of the college.</li>
                        <li>Students who were suspended by the college at any time before the election are not eligible to
                            participate.</li>
                        <li>If a participant receives a college suspension after registering but before the election, their
                            participation will be cancelled.</li>
                        <li>All details and documents submitted during registration must be true and valid.</li>
                        <li>Submission of false information or fake documents will lead to disqualification.</li>
                        <li>Each student can register as a participant only once per election.</li>
                        <li>The final decision regarding participant approval or rejection rests with the Election Admin.
                        </li>
                    </ol>
                </div>

                <form method="POST" enctype="multipart/form-data" id="regForm" autocomplete="off">
                    <label class="confirmation-box" for="rulesCheckbox">
                        <input type="checkbox" name="rules_accepted" id="rulesCheckbox" required>
                        <span class="confirmation-text">I have read and understood the participant eligibility rules</span>
                    </label>

                    <div class="form-section">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="nameInput" class="form-control" required
                            placeholder="Type your Full Name to confirm identity" autocomplete="off">
                        <small id="nameFeedback"
                            style="color: #64748b; font-size: 0.8rem; margin-top: 5px; display: block;">Must match your
                            account name.</small>
                    </div>

                    <div class="form-grid">
                        <div class="form-section">
                            <label class="form-label">College ID</label>
                            <input type="text" name="college_id" id="collegeIdInput" class="form-control" required
                                placeholder="Enter College ID" autocomplete="off">
                        </div>
                        <div class="form-section">
                            <label class="form-label">Department</label>
                            <select name="department" id="deptInput" class="form-control" required>
                                <option value="">Select Department</option>
                                <option value="B.Tech">B.Tech</option>
                                <option value="MCA">MCA</option>
                                <option value="INMCA">Integrated MCA (INMCA)</option>
                                <option value="CSE">Computer Science (CSE)</option>
                                <option value="ECE">Electronics (ECE)</option>
                                <option value="EEE">Electrical (EEE)</option>
                                <option value="MECH">Mechanical (MECH)</option>
                                <option value="CIVIL">Civil (CIVIL)</option>
                                <option value="MT">Metallurgical</option>
                                <option value="FT">Food Technology</option>
                                <option value="CH">Chemical Engineering</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-section">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-section">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-section">
                            <label class="form-label">Year of Study</label>
                            <select name="year" id="yearInput" class="form-control" required>
                                <option value="">Select Year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        <div class="form-section">
                            <label class="form-label">Your Photo (Passport Size)</label>
                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div id="photoPreview"
                                    style="display: none; width: 80px; height: 100px; border: 2px solid #e2e8f0; border-radius: 8px; overflow: hidden; flex-shrink: 0;">
                                    <img id="previewImg" src="" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div style="flex-grow: 1;">
                                    <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png" required
                                        id="photoInput" onchange="previewPhoto(this)">
                                    <small id="photoError"
                                        style="color: #dc2626; font-size: 0.8rem; display: none; margin-top: 5px;"></small>
                                    <small style="color: #64748b; display: block; margin-top: 5px;">Must be a clear
                                        headshot. Max 2MB.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <label class="form-label">Upload ID Proof Photo/Scan</label>
                        <input type="file" name="proof_file" class="form-control" accept=".jpg,.jpeg,.png" required
                            id="proofInput">
                        <small style="color: #64748b; display: block; margin-top: 5px;">Upload a clear scan of your College
                            ID Card. Max 2MB.</small>
                    </div>

                    <div class="form-section">
                        <label class="form-label">Student Signature (Photo/Scan)</label>
                        <input type="file" name="signature" class="form-control" accept=".jpg,.jpeg,.png" required
                            id="sigInput">
                        <small style="color: #666;">Please upload a clear photo of your handwritten signature.</small>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled
                        style="width: 100%; margin-top: 1rem; padding: 1rem; font-weight: 600; opacity: 0.6; cursor: not-allowed;">Complete
                        Registration</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function viewImg(path) {
            window.open(path, '_blank');
        }

        const nameInput = document.getElementById('nameInput');
        const collegeIdInput = document.getElementById('collegeIdInput');
        const deptInput = document.getElementById('deptInput');
        const yearInput = document.getElementById('yearInput');
        const rulesCheckbox = document.getElementById('rulesCheckbox');
        const submitBtn = document.getElementById('submitBtn');

        function updateSubmitState() {
            if (!submitBtn) return;

            const isRulesAccepted = rulesCheckbox ? rulesCheckbox.checked : false;

            const name = nameInput ? nameInput.value.trim() : "";
            const cid = collegeIdInput ? collegeIdInput.value.trim() : "";
            const dept = deptInput ? deptInput.value.trim() : "";
            const year = yearInput ? yearInput.value : "";

            const genderSelect = document.querySelector('select[name="gender"]');
            const dobInput = document.querySelector('input[name="dob"]');

            const isIdentityFilled = (name !== "" && cid !== "" && dept !== "" && year !== "");
            const isGenderValid = (genderSelect && genderSelect.value !== "");
            const isDobValid = (dobInput && dobInput.value !== "");

            const requiredFileInputs = document.querySelectorAll('input[type="file"][required]');
            let allFilesSelected = true;
            requiredFileInputs.forEach(f => {
                if (f.files.length === 0) allFilesSelected = false;
            });

            if (isRulesAccepted && isIdentityFilled && isGenderValid && isDobValid && allFilesSelected) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            }
        }

        // Add listeners to all interactive elements
        document.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', updateSubmitState);
            el.addEventListener('change', updateSubmitState);
        });

        if (rulesCheckbox) rulesCheckbox.addEventListener('change', updateSubmitState);

        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            const previewImg = document.getElementById('previewImg');
            const error = document.getElementById('photoError');
            const file = input.files[0];
            const allowed = ['image/jpeg', 'image/jpg', 'image/png'];

            if (file) {
                if (!allowed.includes(file.type)) {
                    error.innerText = "Invalid format. Use JPG or PNG.";
                    error.style.display = 'block';
                    input.value = '';
                    preview.style.display = 'none';
                } else if (file.size > 2 * 1024 * 1024) {
                    error.innerText = "File too large. Max 2MB.";
                    error.style.display = 'block';
                    input.value = '';
                    preview.style.display = 'none';
                } else {
                    error.style.display = 'none';
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        previewImg.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            } else {
                preview.style.display = 'none';
                error.style.display = 'none';
            }
            updateSubmitState();
        }

        // Initial check
        updateSubmitState();
    </script>
</body>

</html>