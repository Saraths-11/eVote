<?php
session_start();
include 'config.php';

// Authentication Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch Student Profile
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

// 2. Fetch All Relevant Elections
$active_elections = [];
$sql_active = "SELECT * FROM elections 
               WHERE visibleTo = 'students'
               ORDER BY 
               CASE 
                 WHEN status NOT IN ('Completed', 'Closed') THEN 0 
                 ELSE 1 
               END, 
               election_end DESC";
$active_result = $conn->query($sql_active);

if ($active_result) {
    while ($row = $active_result->fetch_assoc()) {
        $active_elections[] = $row;
    }
}

// 3. Fetch Recent Notifications
$notifications = [];
$notif_res = $conn->query("SELECT n.*, e.title as election_title FROM notifications n 
JOIN elections e ON n.election_id = e.id 
LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = $user_id
WHERE nr.id IS NULL
ORDER BY n.created_at DESC LIMIT 5");
if ($notif_res) {
    while ($row = $notif_res->fetch_assoc()) {
        $notifications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - eVote</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        /* Top Navigation */
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .nav-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
        }

        .nav-profile {
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            transition: background 0.2s;
        }

        .nav-profile:hover {
            background: #f8fafc;
        }

        .btn-logout {
            background: #fef2f2;
            color: #ef4444;
            padding: 0.6rem 1.2rem;
            border-radius: 0.75rem;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-logout:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.2);
        }

        .btn-logout:active {
            transform: translateY(0);
        }

        .btn-participation:hover {
            background: #4f46e5 !important;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.2);
        }

        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #eef2ff;
        }

        .nav-name {
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .nav-name:hover {
            color: #4f46e5;
        }

        /* Profile Dropdown Card */
        .profile-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 300px;
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #f1f5f9;
            padding: 1.5rem;
            display: none;
            z-index: 1001;
            animation: slideIn 0.2s ease-out;
        }

        .profile-dropdown.active {
            display: block;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 1.5rem;
            text-align: center;
        }

        .dropdown-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            border: 3px solid #eef2ff;
            display: block;
        }

        .dropdown-detail {
            margin-bottom: 1rem;
        }

        .dropdown-label {
            font-size: 0.7rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }

        .dropdown-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Main Content area */
        .main-content {
            padding: 3rem 2rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-banner {
            background: var(--primary-gradient);
            padding: 3.5rem 3rem;
            border-radius: 2rem;
            color: white;
            margin-bottom: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .hero-banner h1 {
            font-size: 2.25rem;
            font-weight: 800;
            margin: 0;
        }

        .hero-banner p {
            font-size: 1.05rem;
            opacity: 0.9;
            margin-top: 0.6rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1.5rem;
            margin-top: 3.5rem;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .card {
            background: white;
            border-radius: 1.5rem;
            padding: 2.25rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        /* Closed Election Styling (Students Only) */
        .card.closed-election {
            filter: grayscale(1);
            opacity: 0.7;
            border-left: 5px solid #94a3b8 !important;
            background: #fafafa;
        }

        .completed-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 0.4rem 0.8rem;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            margin: 1rem 0 1.5rem;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: #64748b;
        }

        .stat-value {
            font-weight: 800;
            color: #1e293b;
        }

        .winner-box {
            background: #f8fafc;
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid #f1f5f9;
        }

        .winner-label {
            font-size: 0.7rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            display: block;
            margin-bottom: 0.5rem;
        }

        .winner-name-text {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.75rem;
            display: block;
        }

        .progress-container {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-bar-fill {
            height: 100%;
            background: #4f46e5;
            transition: width 1s ease-out;
        }

        .progress-subtext {
            font-size: 0.8rem;
            color: #94a3b8;
            text-align: right;
            font-weight: 600;
        }

        .full-results-btn {
            background: #4f46e5;
            color: white;
            width: 100%;
            padding: 1rem;
            border-radius: 1rem;
            font-weight: 800;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.95rem;
            transition: background 0.2s;
            border: none;
        }

        .full-results-btn:hover {
            background: #4338ca;
        }

        /* Notification Dropdown */
        .noti-wrapper {
            position: relative;
        }

        .noti-bell {
            width: 42px;
            height: 42px;
            background: #f8fafc;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .noti-bell:hover {
            background: #eef2ff;
            border-color: #4f46e5;
            color: #4f46e5;
        }

        .noti-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid white;
        }

        .noti-dropdown {
            position: absolute;
            top: calc(100% + 15px);
            right: 0;
            width: 380px;
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #f1f5f9;
            display: none;
            z-index: 2000;
            animation: slideIn 0.2s ease-out;
            overflow: hidden;
        }

        .noti-dropdown.active {
            display: block;
        }

        .noti-header {
            padding: 1.25rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .noti-header h3 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: #1e293b;
        }

        .noti-list {
            max-height: 450px;
            overflow-y: auto;
        }

        .noti-item {
            padding: 1.25rem;
            border-bottom: 1px solid #f8fafc;
            transition: background 0.2s;
            display: flex;
            gap: 1rem;
            cursor: default;
        }

        .noti-item:hover {
            background: #fafafa;
        }

        .noti-icon {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-top: 6px;
            flex-shrink: 0;
        }

        @media (max-width: 800px) {
            .results-grid {
                grid-template-columns: 1fr;
            }
            .noti-dropdown {
                width: 300px;
                right: -100px;
            }
        }
    </style>
</head>

<body>
    <!-- Top Navigation Bar (Requirement: Title Left, Profile Right) -->
    <nav class="top-nav">
        <div class="nav-title">Student Dashboard</div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            
            <!-- Notification Bell Dropdown -->
            <div class="noti-wrapper">
                <div class="noti-bell" onclick="toggleDropdown('noti-dropdown')">
                    <span style="font-size: 1.25rem;">🔔</span>
                    <?php if (!empty($notifications)): ?>
                        <span class="noti-badge"><?php echo count($notifications); ?></span>
                    <?php endif; ?>
                </div>
                
                <div id="noti-dropdown" class="noti-dropdown">
                    <div class="noti-header">
                        <h3>Latest Election News</h3>
                        <?php if (!empty($notifications)): ?>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #e2e8f0; padding: 2px 8px; border-radius: 10px;">New</span>
                        <?php endif; ?>
                    </div>
                    <div class="noti-list">
                        <?php if (empty($notifications)): ?>
                            <div style="padding: 2rem; text-align: center; color: #94a3b8; font-size: 0.85rem;">
                                No recent notifications
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $noti): ?>
                                <?php 
                                    $dot_color = '#4f46e5';
                                    if ($noti['type'] === 'success') { $dot_color = '#10b981'; }
                                    if ($noti['type'] === 'warning') { $dot_color = '#f59e0b'; }
                                ?>
                                <a href="mark_notification_read.php?id=<?php echo $noti['id']; ?>" style="text-decoration: none; color: inherit;">
                                    <div class="noti-item">
                                        <div class="noti-icon" style="background: <?php echo $dot_color; ?>;"></div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                                                <span style="font-weight: 800; font-size: 0.85rem; color: #1e293b;"><?php echo htmlspecialchars($noti['title']); ?></span>
                                                <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;"><?php echo date('M d', strtotime($noti['created_at'])); ?></span>
                                            </div>
                                            <p style="margin: 0; font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                                <?php echo htmlspecialchars($noti['message']); ?>
                                            </p>
                                            <div style="margin-top: 0.5rem; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;"><?php echo htmlspecialchars($noti['election_title']); ?></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <a href="student_account.php" class="nav-profile" style="text-decoration: none;">
                <?php
                $avatar_url = $current_student['profile_photo'] ? $current_student['profile_photo'] : "https://ui-avatars.com/api/?name=" . urlencode($current_student['accountFullName']) . "&background=4f46e5&color=fff&bold=true";
                ?>
                <img src="<?php echo $avatar_url; ?>" alt="Profile" class="nav-avatar">
                <span class="nav-name">
                    <?php echo htmlspecialchars($current_student['accountFullName']); ?>
                </span>
            </a>
            <a href="logout.php" class="btn-logout">
                <span>Logout</span>
                <span>🚪</span>
            </a>
        </div>
    </nav>

    <main class="main-content">
        <header class="hero-banner">
            <h1>Student Dashboard</h1>
            <p>View published election results and winners.</p>
        </header>


        <!-- Ongoing Elections Section -->
        <section style="margin-bottom: 4rem;">
            <div class="section-header">
                <span>⚡</span> <strong>Ongoing Elections</strong>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div
                    style="background: #FEF2F2; color: #991B1B; padding: 1rem; border-radius: 1rem; border: 1px solid #FEE2E2; margin-bottom: 2rem; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span>⚠️</span>
                    <?php
                    if ($_GET['error'] === 'registration_closed')
                        echo "Registration for this election has ended or is not yet open.";
                    elseif ($_GET['error'] === 'voting_not_active')
                        echo "Voting for this election is not currently active.";
                    elseif ($_GET['error'] === 'access_denied')
                        echo "Access Denied: You must be an Approved Participant to view the candidates list.";
                    else
                        echo "An access error occurred. Please try again.";
                    ?>
                </div>
            <?php endif; ?>

            <?php if (count($active_elections) > 0): ?>
                <div class="results-grid">
                    <?php foreach ($active_elections as $ele):
                        $is_closed = ($ele['status'] === 'Closed');
                        $card_class = $is_closed ? 'card closed-election' : 'card';
                        ?>
                        <div class="<?php echo $card_class; ?>" style="border-left: 5px solid #4f46e5;">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span class="completed-badge" style="background: <?php echo $is_closed ? '#f1f5f9' : '#eef2ff'; ?>; color: <?php echo $is_closed ? '#64748b' : '#4f46e5'; ?>;">
                                    <?php echo $is_closed ? 'CLOSED' : htmlspecialchars($ele['status']); ?>
                                </span>
                                <span style="font-size: 0.8rem; color: #94a3b8; font-weight: 700;">
                                    Ends: <?php echo date('M d, H:i', strtotime($ele['election_end'])); ?>
                                </span>
                            </div>

                            <h3 class="card-title"><?php echo htmlspecialchars($ele['title']); ?></h3>
                            
                            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.6;">
                                <?php echo htmlspecialchars($ele['description']); ?>
                            </p>

                            <div style="display: flex; flex-direction: column; gap: 1rem; background: #f8fafc; padding: 1.25rem; border-radius: 1rem; border: 1px solid #f1f5f9; margin-bottom: 1.5rem;">
                                <?php
                                // Logic for Conditional Display
                                $curr_status = $ele['status'];
                                $v_status = $ele['voting_status'];

                                // Stage 1: Created or Registration Open
                                $show_registration = ($v_status === 'not_started' && ($curr_status === 'active' || $curr_status === 'Registration Open'));

                                // Stage 2: Registration Ended or Nomination Open
                                $show_nomination = ($v_status === 'not_started' && ($curr_status === 'Registration Ended' || $curr_status === 'Nomination Open'));
                                ?>

                                <?php if ($show_registration): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <span style="font-weight: 800; color: #94a3b8; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">Participation Registration</span>
                                        <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">
                                            <?php echo date('M d, H:i', strtotime($ele['registration_start'])); ?> — <?php echo date('M d, H:i', strtotime($ele['registration_end'])); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($show_nomination): ?>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <span style="font-weight: 800; color: #94a3b8; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">Nomination Cancellation</span>
                                        <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">
                                            <?php echo date('M d, H:i', strtotime($ele['nomination_cancellation_start'])); ?> — <?php echo date('M d, H:i', strtotime($ele['nomination_cancellation_end'])); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                    <span style="font-weight: 800; color: #94a3b8; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">Voting Period</span>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">
                                        <?php echo date('M d, H:i', strtotime($ele['election_start'])); ?> — <?php echo date('M d, H:i', strtotime($ele['election_end'])); ?>
                                    </span>
                                </div>
                            </div>

                            <?php
                            // Fetch user's data for this election
                            $stmt_check = $conn->prepare("SELECT status, position FROM participants WHERE election_id = ? AND user_id = ?");
                            $stmt_check->bind_param("ii", $ele['id'], $user_id);
                            $stmt_check->execute();
                            $p_data = $stmt_check->get_result()->fetch_assoc();
                            $participant_status = $p_data['status'] ?? 'Not Enrolled';
                            $participant_position = $p_data['position'] ?? '';

                            $stmt_v = $conn->prepare("SELECT id FROM votes WHERE election_id = ? AND student_id = ?");
                            $stmt_v->bind_param("ii", $ele['id'], $user_id);
                            $stmt_v->execute();
                            $has_voted = $stmt_v->get_result()->fetch_assoc();
                            ?>

                            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: auto;">
                                
                                <!-- Action Row 1: Primary Controls (Equal Size, Same Row) -->
                                <div style="display: flex; gap: 0.75rem; width: 100%;">
                                    <!-- Participation/Voting Logic -->
                                    <?php if ($ele['status'] !== 'Closed' && $ele['status'] !== 'Completed'): ?>
                                        <?php
                                        $reg_status = $ele['registration_status'];
                                        $reg_start = strtotime($ele['registration_start']);
                                        $now = time();
                                        
                                        if ($reg_status === 'open') {
                                            $reg_ui_state = 'open';
                                        } elseif ($reg_status === 'closed' && ($ele['status'] === 'Upcoming' || ($now < $reg_start && $ele['status'] !== 'Registration Ended'))) {
                                            $reg_ui_state = 'upcoming';
                                        } else {
                                            $reg_ui_state = 'ended';
                                        }
                                        ?>

                                        <?php if ($reg_ui_state === 'open'): ?>
                                            <?php if ($participant_status === 'Not Enrolled'): ?>
                                                <a href="register_participant.php?id=<?php echo $ele['id']; ?>" class="full-results-btn" style="background: #10b981; flex: 1; margin: 0; padding: 0.85rem; border: 2px solid transparent; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700;">Register Now</a>
                                            <?php elseif ($participant_status === 'Pending'): ?>
                                                <div style="flex: 1; margin: 0; padding: 0.85rem; border: 2px solid #fef3c7; border-radius: 12px; background: #fffbeb; color: #d97706; font-weight: 600; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">⏳ Pending</div>
                                            <?php elseif ($participant_status === 'Approved'): ?>
                                                <a href="register_participant.php?id=<?php echo $ele['id']; ?>" class="full-results-btn" style="background: #10b981; flex: 1; margin: 0; padding: 0.85rem; border: 2px solid transparent; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700;">View Entry</a>
                                            <?php elseif ($participant_status === 'Rejected' || $participant_status === 'Removed by Admin'): ?>
                                                <div style="flex: 1; margin: 0; padding: 0.85rem; border: 2px solid #fee2e2; border-radius: 12px; background: #fef2f2; color: #ef4444; font-weight: 600; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">❌ Denied</div>
                                            <?php endif; ?>
                                        <?php elseif ($reg_ui_state === 'ended' || $ele['status'] === 'Registration Ended' || $ele['nomination_status'] !== 'not_started'): ?>
                                            <?php if (isset($ele['registration_ended']) && $ele['registration_ended'] == 1): ?>
                                                <a href="participants.php?id=<?php echo $ele['id']; ?>" class="full-results-btn" style="background: #4f46e5; flex: 1; margin: 0; padding: 0.85rem; border: 2px solid transparent; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 700; font-size: 0.95rem; text-decoration: none; white-space: nowrap;"><span>👥</span> Participate</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($ele['voting_status'] === 'active'): ?>
                                                <?php if ($has_voted): ?>
                                                    <div style="flex: 1; margin: 0; padding: 0.85rem; border: 2px solid #d1fae5; border-radius: 12px; background: #ecfdf5; color: #065f46; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">✅ Voted</div>
                                                <?php else: ?>
                                                    <a href="vote.php?id=<?php echo $ele['id']; ?>" class="full-results-btn" style="background: var(--primary-gradient); flex: 1; margin: 0; padding: 0.85rem; border: 2px solid transparent; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 700;">Vote Now</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Result Button (Also in Action Row 1) -->
                                    <?php if ($ele['is_published'] == 1): ?>
                                        <a href="view_results.php?id=<?php echo $ele['id']; ?>" class="full-results-btn" style="background: white; color: #4f46e5; flex: 1; margin: 0; padding: 0.85rem; border: 2px solid #e0e7ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 700; font-size: 0.95rem; text-decoration: none; white-space: nowrap;">📊 View Result</a>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Row 2: Nomination Controls (STRICTLY only during open phase) -->
                                <?php if ($ele['status'] !== 'Closed' && $ele['status'] !== 'Completed' && $ele['nomination_status'] === 'open'): ?>
                                    <?php if ($participant_status === 'Approved'): ?>
                                        <div style="display: flex; width: 100%;">
                                            <a href="cancel_nomination.php?id=<?php echo $ele['id']; ?>" class="full-results-btn" style="background: #fef2f2; color: #ef4444; border: 2px solid #ef4444; flex: 1; margin: 0; padding: 0.85rem; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 700; font-size: 0.95rem; text-decoration: none; white-space: nowrap; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.1);"><span>🚫</span> Withdraw Participation</a>
                                        </div>
                                    <?php elseif ($participant_status === 'Cancelled'): ?>
                                        <div style="width: 100%; text-align: center; font-size: 0.85rem; color: #ef4444; font-weight: 700; background: #fff1f2; padding: 0.85rem; border-radius: 12px; border: 2px solid #fee2e2; display: flex; align-items: center; justify-content: center; gap: 0.5rem; white-space: nowrap;"><span>🔴</span> Participation Withdrawn</div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($ele['voting_status'] === 'ended' && $ele['is_published'] == 0): ?>
                                    <div style="background: #f8fafc; color: #64748b; padding: 1rem; border-radius: 12px; border: 2px dashed #e2e8f0; text-align: center; font-size: 0.85rem; font-weight: 600; width: 100%;">
                                        🕒 Election result has not been published yet.
                                    </div>
                                <?php endif; ?>

                                <?php if ($ele['status'] === 'Closed'): ?>
                                    <div style="text-align: center; padding: 1rem; color: #94a3b8; font-size: 0.85rem; font-weight: 600; border-top: 2px dashed #e2e8f0; margin-top: 0.5rem; width: 100%;">
                                        🔒 Election Archived
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div
                    style="color: #64748b; font-style: italic; background: white; padding: 3rem; border-radius: 1.5rem; border: 1px solid #e2e8f0; text-align: center; width: 100%;">
                    No active elections at the moment.
                </div>
            <?php endif; ?>
        </section>

    </main>

    <script>
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle('active');
            
            // Close when clicking outside
            document.addEventListener('click', function close(e) {
                if (!e.target.closest('.noti-wrapper')) {
                    dropdown.classList.remove('active');
                    document.removeEventListener('click', close);
                }
            });
        }
    </script>
</body>

</html>