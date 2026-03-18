<?php
session_start();
include 'config.php';

// Authentication Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';
$error = '';

// --- Handle Profile Photo Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    $allowed_exts = ['jpg', 'jpeg', 'png'];
    $max_size = 2 * 1024 * 1024; // 2MB

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        $error = "Only JPG and PNG files are allowed.";
    } elseif ($file['size'] > $max_size) {
        $error = "File size must be less than 2MB.";
    } elseif ($file['error'] !== 0) {
        $error = "An error occurred during upload.";
    } else {
        $new_name = "profile_" . $user_id . "_" . time() . "." . $ext;
        $upload_dir = "uploads/profile_photos/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $upload_path = $upload_dir . $new_name;

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Update database
            $stmt_upd = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
            $stmt_upd->bind_param("si", $upload_path, $user_id);
            if ($stmt_upd->execute()) {
                $msg = "Profile photo updated successfully!";
            } else {
                $error = "Failed to update database.";
            }
        } else {
            $error = "Failed to save uploaded file.";
        }
    }
}

// 1. Fetch Logged-in Student Details
// Compatible Database Migration: Ensure profile_photo column exists
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD profile_photo VARCHAR(255) DEFAULT NULL");
}

$stmt_user = $conn->prepare("SELECT accountFullName, email, college_id, department, year, profile_photo FROM users WHERE id = ?");
if (!$stmt_user) {
    die("Database Error: " . $conn->error);
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$current_student = $stmt_user->get_result()->fetch_assoc();

if (!$current_student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 2. Fetch Election History
$election_history = [];
$stmt_history = $conn->prepare("
    SELECT e.title, e.election_end as date, v.id as vote_exists
    FROM elections e
    LEFT JOIN votes v ON e.id = v.election_id AND v.student_id = ?
    WHERE v.id IS NOT NULL OR EXISTS (SELECT 1 FROM participants p2 WHERE p2.user_id = ? AND p2.election_id = e.id)
    ORDER BY e.election_end DESC
");
$stmt_history->bind_param("ii", $user_id, $user_id);
$stmt_history->execute();
$res_history = $stmt_history->get_result();
while ($row = $res_history->fetch_assoc()) {
    $election_history[] = $row;
}

// 3. Fetch Participation Status
$participation_status = [];
$stmt_part = $conn->prepare("
    SELECT p.*, e.title as election_title, e.status as election_status, e.is_published
    FROM participants p
    JOIN elections e ON p.election_id = e.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt_part->bind_param("i", $user_id);
$stmt_part->execute();
$res_part = $stmt_part->get_result();
while ($row = $res_part->fetch_assoc()) {
    if ($row['status'] === 'Approved' && $row['election_status'] === 'Completed' && $row['is_published'] == 1) {
        $e_id = $row['election_id'];
        $sql_winner = "SELECT c.id FROM participants c 
                       LEFT JOIN votes v ON c.id = v.candidate_id 
                       WHERE c.election_id = ? 
                       GROUP BY c.id 
                       ORDER BY COUNT(v.id) DESC LIMIT 1";
        $stmt_w = $conn->prepare($sql_winner);
        $stmt_w->bind_param("i", $e_id);
        $stmt_w->execute();
        $win_res = $stmt_w->get_result()->fetch_assoc();

        $row['final_result'] = ($win_res && $win_res['id'] == $row['id']) ? 'Winner' : 'Lost';
    } else {
        $row['final_result'] = 'N/A';
    }
    $participation_status[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Account Dashboard - eVote</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --bg-body: #f8fafc;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .top-nav {
            background: white;
            padding: 0 2rem;
            height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
            text-decoration: none;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            .container {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar Styles */
        .sidebar {
            background: white;
            padding: 1.5rem;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
            height: fit-content;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu-item {
            margin-bottom: 0.5rem;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            text-decoration: none;
            color: #64748b;
            font-weight: 600;
            transition: all 0.2s;
        }

        .menu-link:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .menu-link.active {
            background: #eef2ff;
            color: var(--primary);
        }

        .menu-link.logout {
            color: #ef4444;
            margin-top: 2rem;
            border-top: 1px solid #f1f5f9;
            padding-top: 1.5rem;
            border-radius: 0;
        }

        /* Content Area */
        .content-area {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Profile Summary Section */
        .profile-summary-header {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            border-radius: 1.5rem;
            padding: 2.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .profile-summary-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: linear-gradient(45deg, transparent 0%, rgba(255, 255, 255, 0.1) 100%);
            transform: skewX(-15deg);
        }

        .profile-main-info {
            display: flex;
            align-items: center;
            gap: 2rem;
            z-index: 1;
        }

        .profile-avatar-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .avatar-edit-badge {
            position: absolute;
            bottom: 0;
            right: 0;
            background: white;
            color: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s;
            border: none;
        }

        .avatar-edit-badge:hover {
            transform: scale(1.1);
        }

        .profile-text h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .profile-text p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .account-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
        }

        .card-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 1rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            margin: 0;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            padding: 1rem 1.25rem;
            background: #f8fafc;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            transition: border-color 0.2s;
        }

        .info-value:hover {
            border-color: #cbd5e1;
        }

        /* Form Overlay */
        #photoFormOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .photo-modal {
            background: white;
            padding: 2.5rem;
            border-radius: 2rem;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
        }

        .photo-modal h3 {
            margin: 0 0 1.5rem;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .file-input-wrapper {
            border: 2px dashed #e2e8f0;
            padding: 2rem;
            border-radius: 1.5rem;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-input-wrapper:hover {
            border-color: var(--primary);
            background: #f5f3ff;
        }

        .btn-upload {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }

        .btn-upload:hover {
            background: var(--primary-dark);
        }

        /* Success/Error Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Table Styles (Consolidated from previous) */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 1.25rem 1rem;
            background: #f8fafc;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table td {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-voted,
        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-notvoted,
        .status-rejected,
        .status-removed-by-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .status-cancelled {
            background: #f1f5f9;
            color: #64748b;
        }

        .status-winner {
            background: #4f46e5;
            color: white;
        }

        .status-lost {
            background: #f1f5f9;
            color: #64748b;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <nav class="top-nav">
        <a href="student_dashboard.php" class="nav-title">eVote Student Portal</a>
        <div style="font-weight: 700; color: #64748b;">Logged in as:
            <?php echo htmlspecialchars($current_student['accountFullName']); ?>
        </div>
    </nav>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="student_dashboard.php" class="menu-link">
                        <span style="font-size: 1.25rem;">🏠</span> Dashboard Home
                    </a>
                </li>
                <li class="menu-item">
                    <a href="javascript:void(0)" class="menu-link active" onclick="showTab('profile', this)">
                        <span style="font-size: 1.25rem;">👤</span> Profile Information
                    </a>
                </li>
                <li class="menu-item">
                    <a href="javascript:void(0)" class="menu-link" onclick="showTab('history', this)">
                        <span style="font-size: 1.25rem;">🗳️</span> Election History
                    </a>
                </li>
                <li class="menu-item">
                    <a href="javascript:void(0)" class="menu-link" onclick="showTab('participation', this)">
                        <span style="font-size: 1.25rem;">🎖️</span> Participation Status
                    </a>
                </li>
                <li class="menu-item">
                    <a href="logout.php" class="menu-link logout">
                        <span style="font-size: 1.25rem;">Logout</span>
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="content-area">
            <?php if ($msg): ?>
                <div class="alert alert-success">✅ <?php echo $msg; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Profile Summary Section -->
            <div class="profile-summary-header">
                <div class="profile-main-info">
                    <div class="profile-avatar-wrapper">
                        <?php
                        $avatar_url = $current_student['profile_photo'] ? $current_student['profile_photo'] : "https://ui-avatars.com/api/?name=" . urlencode($current_student['accountFullName']) . "&background=fff&color=4f46e5&bold=true";
                        ?>
                        <img src="<?php echo $avatar_url; ?>" alt="Profile" class="profile-avatar" id="currentAvatar">
                        <button class="avatar-edit-badge" onclick="openPhotoModal()" title="Update Profile Photo">
                            📷
                        </button>
                    </div>
                    <div class="profile-text">
                        <h2><?php echo htmlspecialchars($current_student['accountFullName']); ?></h2>
                        <p><?php echo htmlspecialchars($current_student['college_id']); ?> •
                            <?php echo htmlspecialchars($current_student['department'] ?: 'Information Technology'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Profile Content (Read-Only) -->
            <div id="profile" class="tab-content active">
                <div class="account-card">
                    <div class="card-header">
                        <h3 class="card-title">Personal Details</h3>
                        <span style="font-size: 0.8rem; color: #94a3b8; font-weight: 700;">READ-ONLY ACCESS</span>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label class="info-label">Full Name</label>
                            <div class="info-value"><?php echo htmlspecialchars($current_student['accountFullName']); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label class="info-label">Login Email ID</label>
                            <div class="info-value"><?php echo htmlspecialchars($current_student['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <label class="info-label">College ID</label>
                            <div class="info-value"><?php echo htmlspecialchars($current_student['college_id']); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label class="info-label">Department</label>
                            <div class="info-value">
                                <?php echo htmlspecialchars($current_student['department'] ?: 'IT Department'); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label class="info-label">Year of Study</label>
                            <div class="info-value">
                                <?php echo htmlspecialchars($current_student['year'] ?: '3rd Year'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Election History -->
            <div id="history" class="tab-content">
                <div class="account-card">
                    <div class="card-header">
                        <h3 class="card-title">Voting Records</h3>
                    </div>
                    <?php if (count($election_history) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Election Name</th>
                                        <th>Election Date</th>
                                        <th>Vote Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($election_history as $history): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($history['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($history['date'])); ?></td>
                                            <td>
                                                <span
                                                    class="status-badge <?php echo $history['vote_exists'] ? 'status-voted' : 'status-notvoted'; ?>">
                                                    <?php echo $history['vote_exists'] ? 'Voted' : 'Not Voted'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 4rem; color: #94a3b8;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🗳️</div>
                            <h3>No Voting Records</h3>
                            <p>You haven't participated in any elections yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Participation Status -->
            <div id="participation" class="tab-content">
                <div class="account-card">
                    <div class="card-header">
                        <h3 class="card-title">Candidate Profile Status</h3>
                    </div>
                    <?php if (count($participation_status) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Election Name</th>
                                        <th>Candidate Role</th>
                                        <th>Status Details</th>
                                        <th>Final Outcome</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participation_status as $part): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 700; color: #1e293b;">
                                                    <?php echo htmlspecialchars($part['election_title']); ?>
                                                </div>
                                                <?php if ($part['status'] === 'Approved' && $part['approved_at']): ?>
                                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 5px;">
                                                        Approved on: <?php echo date('M d, Y', strtotime($part['approved_at'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: #4f46e5;">
                                                    <?php echo htmlspecialchars($part['candidate_role'] ?? 'Student Candidate'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                                    <span
                                                        class="status-badge status-<?php echo str_replace(' ', '-', strtolower($part['status'])); ?>"
                                                        style="width: fit-content;">
                                                        <?php echo htmlspecialchars($part['status']); ?>
                                                    </span>

                                                    <?php if ($part['status'] === 'Rejected' && !empty($part['rejection_reason'])): ?>
                                                        <div
                                                            style="font-size: 0.8rem; color: #ef4444; background: #fee2e2; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #fecaca;">
                                                            <strong>Reason:</strong>
                                                            <?php echo htmlspecialchars($part['rejection_reason']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($part['status'] === 'Removed by Admin' && !empty($part['removal_reason'])): ?>
                                                        <div
                                                            style="font-size: 0.8rem; color: #ef4444; background: #fee2e2; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #fecaca;">
                                                            <strong>Admin Removal Reason:</strong>
                                                            <?php echo htmlspecialchars($part['removal_reason']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($part['status'] === 'Cancelled' && !empty($part['cancellation_reason'])): ?>
                                                        <div
                                                            style="font-size: 0.8rem; color: #64748b; background: #f1f5f9; padding: 0.5rem; border-radius: 0.5rem;">
                                                            <strong>Cancellation Note:</strong>
                                                            <?php echo htmlspecialchars($part['cancellation_reason']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($part['final_result'] !== 'N/A'): ?>
                                                    <span
                                                        class="status-badge status-<?php echo strtolower($part['final_result']); ?>">
                                                        <?php echo htmlspecialchars($part['final_result']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-size: 0.85rem; font-style: italic;">Evaluation
                                                        Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 4rem; color: #94a3b8;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🎖️</div>
                            <h3>No Participation Data</h3>
                            <p>You haven't applied as a candidate in any elections.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Photo Upload Modal -->
    <div id="photoFormOverlay">
        <div class="photo-modal">
            <h3>Update Profile Photo</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem; font-size: 0.9rem;">Upload a professional photo (JPG/PNG,
                max 2MB).</p>
            <form method="POST" enctype="multipart/form-data">
                <div class="file-input-wrapper" onclick="document.getElementById('fileInput').click()">
                    <span id="fileNameDisp">Click to Select Image</span>
                    <input type="file" id="fileInput" name="profile_photo" hidden accept=".jpg,.jpeg,.png"
                        onchange="updateFileName(this)">
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn-upload" style="background: #f1f5f9; color: #64748b;"
                        onclick="closePhotoModal()">Cancel</button>
                    <button type="submit" class="btn-upload">Upload Photo</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabId, element) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelectorAll('.menu-link').forEach(link => link.classList.remove('active'));
            element.classList.add('active');
        }

        // Handle Tab Switching via URL Param
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tabProfile = urlParams.get('tab');
            if (tabProfile) {
                const link = document.querySelector(`a[onclick*="'${tabProfile}'"]`);
                if (link) showTab(tabProfile, link);
            }
        });

        function openPhotoModal() {
            document.getElementById('photoFormOverlay').style.display = 'flex';
        }

        function closePhotoModal() {
            document.getElementById('photoFormOverlay').style.display = 'none';
        }

        function updateFileName(input) {
            if (input.files && input.files[0]) {
                document.getElementById('fileNameDisp').innerText = input.files[0].name;
            }
        }

        // Close modal on click outside
        window.onclick = function (event) {
            let modal = document.getElementById('photoFormOverlay');
            if (event.target == modal) closePhotoModal();
        }
    </script>
</body>

</html>