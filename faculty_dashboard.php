<?php
session_start();
include 'config.php';

// -----------------------------------
// 1. AUTHENTICATION & SECURITY
// -----------------------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: login.php");
    exit();
}

// Extra security: Validate email domain again
if (!str_ends_with($_SESSION['email'], '@amaljyothi.ac.in')) {
    session_destroy();
    header("Location: login.php?error=UnauthorizedAccess");
    exit();
}

$faculty_name = $_SESSION['name'];
$current_tab = $_GET['tab'] ?? 'dashboard';

// Faculty is read-only for participants. Recommendation logic removed as Admin decisions are final.
$message = '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - College Election System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Faculty Dashboard Specific Styles */
        :root {
            --faculty-primary: #3b82f6;
            /* Neutral Blue */
            --faculty-dark: #1e3a8a;
            --faculty-bg: #f3f4f6;
            --faculty-sidebar: #ffffff;
            --faculty-text: #1f2937;
        }

        body {
            background-color: var(--faculty-bg);
            display: flex;
            /* Sidebar layout */
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background-color: var(--faculty-sidebar);
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 10;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: var(--faculty-dark);
            color: white;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            margin: 0;
            font-weight: 600;
        }

        .sidebar-menu {
            flex: 1;
            padding: 1rem 0;
            list-style: none;
        }

        .sidebar-menu a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: var(--faculty-text);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: #eff6ff;
            color: var(--faculty-primary);
            border-left-color: var(--faculty-primary);
        }

        .user-profile {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .user-profile .name {
            font-weight: 600;
            font-size: 0.9rem;
            display: block;
        }

        .logout-btn {
            color: #ef4444;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-block;
            margin-top: 0.25rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            /* Width of sidebar */
            flex: 1;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--faculty-text);
            margin-bottom: 0.5rem;
        }

        /* Cards and Tables */
        .content-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th,
        .data-table td {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .data-table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #4b5563;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-upcoming {
            background: #e0f2fe;
            color: #0284c7;
        }

        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-completed {
            background: #f3f4f6;
            color: #4b5563;
        }

        /* Buttons */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .btn-approve {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .btn-reject {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            padding: 1rem;
            background: #dcfce7;
            color: #166534;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        /* Progress Bar */
        .progress-bar-bg {
            background: #f1f5f9;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar-fill {
            background: var(--faculty-primary);
            height: 100%;
            transition: width 0.5s ease-in-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                overflow: hidden;
            }

            .sidebar-header h2,
            .sidebar-menu a span,
            .user-profile {
                display: none;
            }

            .main-content {
                margin-left: 60px;
            }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Amal Jyothi College</h2>
        </div>
        <nav class="sidebar-menu">
            <a href="?tab=dashboard" class="<?php echo $current_tab == 'dashboard' ? 'active' : ''; ?>">🏠 Dashboard</a>
            <a href="?tab=elections" class="<?php echo $current_tab == 'elections' ? 'active' : ''; ?>">🗳️ View
                Elections</a>
            <a href="?tab=candidates" class="<?php echo $current_tab == 'candidates' ? 'active' : ''; ?>">👥 Approved
                Candidates</a>
            <a href="?tab=rejected" class="<?php echo $current_tab == 'rejected' ? 'active' : ''; ?>">❌ Rejected
                Students</a>
            <a href="?tab=withdrawn" class="<?php echo $current_tab == 'withdrawn' ? 'active' : ''; ?>">🚫 Withdrawn Participants</a>
            <a href="?tab=monitor" class="<?php echo $current_tab == 'monitor' ? 'active' : ''; ?>">📊 Voting
                Monitor</a>
            <a href="?tab=results" class="<?php echo $current_tab == 'results' ? 'active' : ''; ?>">🏆 Results</a>
            <a href="?tab=past" class="<?php echo $current_tab == 'past' ? 'active' : ''; ?>">🕰️ Past Elections</a>
        </nav>
        <div class="user-profile">
            <span class="name">
                <?php echo htmlspecialchars($faculty_name); ?>
            </span>
            <small>Faculty Member</small><br>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <?php if ($message): ?>
            <div class="alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php switch ($current_tab):

            // -----------------------------------
            // CASE: DASHBOARD (HOME)
            // -----------------------------------
            case 'dashboard':
                // Fetch stats
                $active_elec = $conn->query("SELECT COUNT(*) as count FROM elections WHERE status IN ('active', 'Voting Active')")->fetch_assoc()['count'];
                $pending_ver = $conn->query("SELECT COUNT(*) as count FROM participants WHERE status = 'Pending'")->fetch_assoc()['count'];
                $total_elec = $conn->query("SELECT COUNT(*) as count FROM elections")->fetch_assoc()['count'];
                $completed_elec = $conn->query("SELECT COUNT(*) as count FROM elections WHERE status IN ('Completed', 'Closed')")->fetch_assoc()['count'];
                ?>
                <div class="page-header">
                    <h1 class="page-title">Faculty Dashboard Overview</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($faculty_name); ?>. Here is what's happening today.</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                    <!-- Quick Access Cards -->
                    <a href="?tab=elections" style="text-decoration: none; color: inherit;">
                        <div class="content-card" style="border-top: 4px solid #3b82f6;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🗳️</div>
                            <h3 style="margin: 0; color: #1e3a8a;">Elections Created</h3>
                            <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0;"><?php echo $total_elec; ?></p>
                            <p style="color: #6b7280; font-size: 0.9rem;">Total elections in the system.</p>
                        </div>
                    </a>

                    <a href="?tab=monitor" style="text-decoration: none; color: inherit;">
                        <div class="content-card" style="border-top: 4px solid #10b981;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📊</div>
                            <h3 style="margin: 0; color: #166534;">Active Voting</h3>
                            <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0;"><?php echo $active_elec; ?></p>
                            <p style="color: #6b7280; font-size: 0.9rem;">Elections currently live.</p>
                        </div>
                    </a>

                    <a href="?tab=verify" style="text-decoration: none; color: inherit;">
                        <div class="content-card" style="border-top: 4px solid #f59e0b;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📋</div>
                            <h3 style="margin: 0; color: #92400e;">Pending Verifications</h3>
                            <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0;"><?php echo $pending_ver; ?></p>
                            <p style="color: #6b7280; font-size: 0.9rem;">Students waiting for review.</p>
                        </div>
                    </a>

                    <a href="?tab=past" style="text-decoration: none; color: inherit;">
                        <div class="content-card" style="border-top: 4px solid #6b7280;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🕰️</div>
                            <h3 style="margin: 0; color: #374151;">Past Elections</h3>
                            <p style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0;"><?php echo $completed_elec; ?></p>
                            <p style="color: #6b7280; font-size: 0.9rem;">Completed and archived.</p>
                        </div>
                    </a>
                </div>

                <div class="content-card" style="margin-top: 2rem;">
                    <h3>⚡ Recent Activity</h3>
                    <p style="color: #6b7280; font-size: 0.9rem;">Last 5 elections created.</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Election</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent = $conn->query("SELECT * FROM elections ORDER BY created_at DESC LIMIT 5");
                            while ($row = $recent->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td>
                                        <span class="status-badge 
                                        <?php
                                        if ($row['status'] == 'active' || $row['status'] == 'Voting Active')
                                            echo 'status-active';
                                        elseif ($row['status'] == 'Completed' || $row['status'] == 'Closed')
                                            echo 'status-completed';
                                        else
                                            echo 'status-upcoming';
                                        ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php break; ?>

                <?php
            // -----------------------------------
            // CASE: VIEW ELECTIONS (Read-only)
            // -----------------------------------
            case 'elections':
                $result = $conn->query("SELECT * FROM elections ORDER BY created_at DESC");
                ?>
                <div class="page-header">
                    <h1 class="page-title">All Elections</h1>
                    <p>View details of all elections in the system.</p>
                </div>

                <div class="content-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Registration</th>
                                <th>Voting</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                        <small
                                            style="color: #6b7280;"><?php echo substr(htmlspecialchars($row['description']), 0, 50); ?>...</small>
                                    </td>
                                    <td>
                                        <span class="status-badge 
                                        <?php
                                        if ($row['status'] == 'active' || $row['status'] == 'Voting Active')
                                            echo 'status-active';
                                        elseif ($row['status'] == 'Completed' || $row['status'] == 'Closed')
                                            echo 'status-completed';
                                        else
                                            echo 'status-upcoming';
                                        ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d', strtotime($row['registration_start'])); ?> -
                                        <?php echo date('M d', strtotime($row['registration_end'])); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d', strtotime($row['election_start'])); ?> -
                                        <?php echo date('M d', strtotime($row['election_end'])); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php break; ?>

                <?php
            // -----------------------------------
            // CASE: APPROVED CANDIDATES (Read-only)
            // -----------------------------------
            case 'candidates':
                $result = $conn->query("SELECT p.*, e.title as election_title FROM participants p JOIN elections e ON p.election_id = e.id WHERE p.status = 'Approved' ORDER BY e.title ASC, p.name ASC");
                ?>
                <div class="page-header">
                    <h1 class="page-title">Approved Candidates</h1>
                    <p>Official list of participants cleared to contest in their respective elections.</p>
                </div>

                <div class="content-card">
                    <?php if ($result->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Election</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="display: flex; gap: 0.75rem; align-items: center;">
                                            <div
                                                style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; background: #eee;">
                                                <img src="<?php echo htmlspecialchars($row['photo_path']); ?>"
                                                    style="width:100%; height:100%; object-fit:cover;">
                                            </div>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['election_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?> (Year <?php echo $row['year']; ?>)</td>
                                        <td><span class="status-badge status-active">Approved</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No approved candidates found.</p>
                    <?php endif; ?>
                </div>
                <?php break; ?>

                <?php
            // -----------------------------------
            // CASE: REJECTED STUDENTS (Read-only)
            // -----------------------------------
            case 'rejected':
                $result = $conn->query("SELECT p.*, e.title as election_title FROM participants p JOIN elections e ON p.election_id = e.id WHERE p.status = 'Rejected' ORDER BY e.title ASC, p.name ASC");
                ?>
                <div class="page-header">
                    <h1 class="page-title">Rejected Participant Requests</h1>
                    <p>Students whose registration requests were not cleared by the Admin.</p>
                </div>

                <div class="content-card">
                    <?php if ($result->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Election</th>
                                    <th>Department</th>
                                    <th>Reason for Rejection</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="display: flex; gap: 0.75rem; align-items: center;">
                                            <div
                                                style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; background: #eee; filter: grayscale(100%);">
                                                <img src="<?php echo htmlspecialchars($row['photo_path']); ?>"
                                                    style="width:100%; height:100%; object-fit:cover;">
                                            </div>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['election_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td>
                                            <div
                                                style="color: #991b1b; font-size: 0.85rem; background: #fef2f2; padding: 0.5rem; border-radius: 6px; border: 1px solid #fee2e2;">
                                                <?php echo htmlspecialchars($row['rejection_reason'] ?: 'No reason provided.'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No rejected participants found.</p>
                    <?php endif; ?>
                </div>
                <?php break; ?>

                <?php
            // -----------------------------------
            // CASE: WITHDRAWN PARTICIPANTS (Read-only)
            // -----------------------------------
            case 'withdrawn':
                $result = $conn->query("SELECT p.*, e.title as election_title FROM participants p JOIN elections e ON p.election_id = e.id WHERE p.status = 'Cancelled' ORDER BY p.cancelled_at DESC");
                ?>
                <div class="page-header">
                    <h1 class="page-title">Withdrawn Participants</h1>
                    <p>Students who cancelled their participation during the nomination period.</p>
                </div>

                <div class="content-card">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Election</th>
                                    <th>Withdrawal Date</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="display: flex; gap: 0.75rem; align-items: center;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; background: #eee;">
                                                <img src="<?php echo htmlspecialchars($row['photo_path']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                                <small style="color: #64748b; font-family: monospace;"><?php echo $row['college_id']; ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['election_title']); ?></td>
                                        <td>
                                            <div style="font-weight: 600; color: #4b5563;">
                                                <?php echo $row['cancelled_at'] ? date('M d, Y', strtotime($row['cancelled_at'])) : 'N/A'; ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #94a3b8;">
                                                <?php echo $row['cancelled_at'] ? date('h:i A', strtotime($row['cancelled_at'])) : ''; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="color: #6b7280; font-size: 0.85rem; font-style: italic;">
                                                "<?php echo htmlspecialchars($row['cancellation_reason'] ?: 'No reason provided.'); ?>"
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No withdrawn participants found.</p>
                    <?php endif; ?>
                </div>

                <?php
            // -----------------------------------
            // CASE: VOTING MONITOR
            // -----------------------------------
            case 'monitor':
                // Logic: Get active elections, show counts. Blind means no voter info.
                $elections = $conn->query("SELECT id, title FROM elections WHERE status NOT IN ('Completed', 'Closed') OR voting_status = 'active'");
                ?>
                <div class="page-header">
                    <h1 class="page-title">Voting Monitor</h1>
                    <p>Live vote counts and participation tracking (Anonymous).</p>
                </div>

                <?php if ($elections && $elections->num_rows > 0): ?>
                    <?php while ($elec = $elections->fetch_assoc()): ?>
                    <div class="content-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="margin: 0;"><?php echo htmlspecialchars($elec['title']); ?></h3>
                        </div>
                        <?php
                        $eid = $elec['id'];
                        // Get total votes
                        $total_votes_res = $conn->query("SELECT COUNT(*) as count FROM votes WHERE election_id = $eid");
                        $total_votes = $total_votes_res->fetch_assoc()['count'] ?: 0;
                        // Calculate total votes and continue

                        // Get candidates and vote counts
                        $sql = "SELECT p.name, COUNT(v.id) as vote_count 
                                FROM participants p 
                                LEFT JOIN votes v ON p.id = v.candidate_id 
                                WHERE p.election_id = $eid AND p.status = 'Approved'
                                GROUP BY p.id";
                        $cands = $conn->query($sql);
                        ?>
                        <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #475569;">
                            Total Votes Cast: <strong><?php echo $total_votes; ?></strong>
                        </div>
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
                            <?php while ($cand = $cands->fetch_assoc()):
                                $percent = ($total_votes > 0) ? round(($cand['vote_count'] / $total_votes) * 100, 2) : 0;
                                $percent = min(100, $percent);
                                ?>
                                <div style="background: #f9fafb; padding: 1.25rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <span
                                            style="font-weight: 600; color: var(--faculty-text);"><?php echo htmlspecialchars($cand['name']); ?></span>
                                        <span
                                            style="font-weight: 700; color: var(--faculty-primary);"><?php echo $cand['vote_count']; ?>
                                            votes</span>
                                    </div>
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    <div style="text-align: right; font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                        <?php echo $percent; ?>% of total votes
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div style="margin-top: 2rem; text-align: center;">
                            <a href="view_results.php?id=<?php echo $elec['id']; ?>" class="btn btn-sm btn-approve"
                                style="padding: 0.6rem 1.2rem; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5rem;">
                                📊 View Detailed Graphical Report
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="content-card">
                        <p style="text-align: center; color: #6b7280; padding: 2rem;">No active voting currently taking place.</p>
                    </div>
                <?php endif; ?>
                <?php break; ?>

                <?php
            // -----------------------------------
            // CASE: RESULTS
            // -----------------------------------
            case 'results':
                $elections = $conn->query("SELECT id, title FROM elections WHERE status IN ('Completed', 'Closed') AND is_published = 1");
                ?>
                <div class="page-header">
                    <h1 class="page-title">Election Results</h1>
                    <p>Final standings and participation analysis for completed elections.</p>
                </div>

                <?php if ($elections->num_rows > 0): ?>
                    <?php while ($elec = $elections->fetch_assoc()): ?>
                        <div class="content-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                <h3 style="margin: 0;"><?php echo htmlspecialchars($elec['title']); ?></h3>
                            </div>
                            <?php
                            $eid = $elec['id'];
                            // Get total votes
                            $total_votes_res = $conn->query("SELECT COUNT(*) as count FROM votes WHERE election_id = $eid");
                            $total_votes = $total_votes_res->fetch_assoc()['count'] ?: 0;
                            // Get total votes

                            $sql = "SELECT p.name, COUNT(v.id) as vote_count 
                                    FROM participants p 
                                    LEFT JOIN votes v ON p.id = v.candidate_id 
                                    WHERE p.election_id = $eid AND p.status = 'Approved'
                                    GROUP BY p.id 
                                    ORDER BY vote_count DESC";
                            $cands = $conn->query($sql);
                            $highest = -1;
                            $results_data = [];
                            while ($row = $cands->fetch_assoc()) {
                                $results_data[] = $row;
                                if ($row['vote_count'] > $highest)
                                    $highest = $row['vote_count'];
                            }
                            ?>
                            <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #475569;">
                                Total Votes Cast: <strong><?php echo $total_votes; ?></strong>
                            </div>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Votes Received</th>
                                        <th>Percentage (of Total)</th>
                                        <th>Outcome</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results_data as $cand):
                                        $percent = ($total_votes > 0) ? round(($cand['vote_count'] / $total_votes) * 100, 2) : 0;
                                        $percent = min(100, $percent);
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($cand['name']); ?></strong></td>
                                            <td><?php echo $cand['vote_count']; ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="progress-bar-bg" style="flex: 1; margin: 0;">
                                                        <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                                    </div>
                                                    <span
                                                        style="font-size: 0.85rem; font-weight: 600; min-width: 50px;"><?php echo $percent; ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($cand['vote_count'] == $highest && $highest > 0): ?>
                                                    <span class="status-badge" style="background:#fef3c7; color:#d97706;">WINNER 🏆</span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top: 2rem; text-align: center;">
                                <a href="view_results.php?id=<?php echo $elec['id']; ?>" class="btn btn-sm btn-approve"
                                    style="padding: 0.6rem 1.2rem; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    📊 View Analytics & Graphs
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="content-card">No published election results yet.</div>
                <?php endif; ?>
                <?php break; ?>

                <?php
            // -----------------------------------
            // CASE: PAST ELECTIONS
            // -----------------------------------
            case 'past':
                $result = $conn->query("SELECT * FROM elections WHERE status IN ('Completed', 'Closed')");
                ?>
                <div class="page-header">
                    <h1 class="page-title">Past Elections Archive</h1>
                </div>
                <div class="content-card">
                    <!-- Black & White UI applied via filter style or just grayscale -->
                    <table class="data-table" style="filter: grayscale(100%);">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date Ended</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($row['title']); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($row['election_end'])); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php break; ?>

        <?php endswitch; ?>
    </main>

</body>

</html>