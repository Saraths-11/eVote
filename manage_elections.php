<?php
session_start();
include 'config.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Auto-fix: Ensure all required columns exist
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS createdBy VARCHAR(50) DEFAULT 'admin'");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS visibleTo VARCHAR(50) DEFAULT 'students'");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS is_published TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS nomination_status ENUM('not_started', 'open', 'closed') DEFAULT 'not_started'");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS show_voters TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS registration_ended TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS nomination_ended TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS voting_ended TINYINT(1) DEFAULT 0");


// Data Repair: Fix existing elections to match visibility requirements
$conn->query("UPDATE elections SET visibleTo = 'students' WHERE visibleTo IS NULL OR visibleTo = ''");

// Fetch Elections
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.id) as total_votes,
        (SELECT COUNT(*) FROM participants p WHERE p.election_id = e.id AND p.status = 'Approved') as approved_candidates
        FROM elections e ORDER BY created_at DESC";
$result = $conn->query($sql);

$has_active = $conn->query("SELECT id FROM elections WHERE status != 'Closed' LIMIT 1")->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections | eVote Admin</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: var(--text-main);
            border: 1px solid #E5E7EB;
        }

        .btn-secondary:hover {
            background: var(--secondary);
        }

        .btn-danger {
            background: #FEF2F2;
            color: var(--error);
            border: 1px solid #FEE2E2;
        }

        .btn-danger:hover {
            background: #FEE2E2;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #F9FAFB;
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #F3F4F6;
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }

        .election-title {
            font-weight: 600;
            color: var(--text-main);
            display: block;
            margin-bottom: 0.25rem;
        }

        .election-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-upcoming {
            background: #FDE68A;
            color: #92400E;
        }

        .status-reg {
            background: #DBEAFE;
            color: #1E40AF;
        }

        .status-active {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-completed {
            background: #F3F4F6;
            color: #374151;
        }

        .time-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .time-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #9CA3AF;
        }

        .actions {
            display: flex;
            gap: 0.75rem;
        }

        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid #D1FAE5;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="nav-brand">eVote Admin</div>
        <div class="nav-actions">
            <a href="admin_dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="logout.php" class="btn btn-secondary" style="color: var(--error);">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($msg === 'created'): ?>
            <div class="alert alert-success">✨ New election created successfully!</div>
        <?php elseif ($msg === 'updated'): ?>
            <div class="alert alert-success">✨ Election status updated!</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"
                style="background: #FEF2F2; color: #991B1B; border: 1px solid #FEE2E2; padding: 1rem; border-radius: 12px; margin-bottom: 2rem;">
                ⚠️ <?php
                switch ($error) {
                    case 'reg_not_ended':
                        echo "Action Blocked: Participation Registration must be ended before starting Voting.";
                        break;
                    case 'election_not_ended':
                        echo "Action Blocked: The election must be officially ended before results can be published.";
                        break;
                    case 'reg_already_ended':
                        echo "Action Blocked: Registration has already ended and cannot be started again for this election.";
                        break;
                    case 'cannot_restart_reg':
                        echo "Action Blocked: Registration cannot be reopened once voting has started or ended.";
                        break;
                    case 'nomination_needs_reg_end':
                        echo "Action Blocked: Nomination can only start after Registration has ended.";
                        break;
                    case 'vote_needs_nomination_end':
                        echo "Action Blocked: Election Voting can only start after Nomination period has ended.";
                        break;
                    case 'no_approved_candidates':
                        echo "Action Blocked: Voting cannot be started because no approved candidates are available.";
                        break;
                    case 'already_published':
                        echo "Action Blocked: The election result has already been published and cannot be unpublished.";
                        break;
                    case 'cannot_edit_after_voting':
                        echo "Election details cannot be edited after voting has started.";
                        break;
                    case 'vote_not_ended_cannot_close':
                        echo "Action Blocked: Election can only be closed after voting has ended.";
                        break;
                    case 'election_closed_locked':
                        echo "This election is closed and cannot be modified.";
                        break;
                    case 'nomination_already_ended':
                        echo "Action Blocked: Nomination period has already ended and cannot be restarted.";
                        break;
                    case 'results_not_published':
                        echo "Action Blocked: Election results must be published before you can close the election.";
                        break;
                    case 'no_approved_participants':
                        echo "Action Blocked: Nomination cannot start until at least one participant has been approved.";
                        break;
                    default:
                        echo "An error occurred with the workflow sequence.";
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h1>Election Management</h1>
            <?php if (!$has_active): ?>
                <a href="create_election.php" class="btn btn-primary">+ Create Election</a>
            <?php else: ?>
                <button class="btn btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed;"
                    title="You must close the current election before creating a new one">+ Create Election</button>
            <?php endif; ?>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Election</th>
                        <th>Participation</th>
                        <th>Live Status</th>
                        <th>Schedule</th>
                        <th>Quick Controls</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()):
                            $status = $row['status'];
                            $badgeClass = 'status-upcoming';
                            if ($status == 'Registration Open')
                                $badgeClass = 'status-reg';
                            if ($status == 'Voting Active' || $status == 'active')
                                $badgeClass = 'status-active';
                            if ($status == 'Completed' || $status == 'Closed')
                                $badgeClass = 'status-completed';
                            if ($status == 'Registration Ended' || $status == 'Nomination Ended')
                                $badgeClass = 'status-completed';
                            if ($status == 'Nomination Open')
                                $badgeClass = 'status-reg';

                            $is_published = isset($row['is_published']) ? $row['is_published'] : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="election-title"><?php echo htmlspecialchars($row['title']); ?></span>
                                    <span class="election-desc"><?php echo htmlspecialchars($row['description']); ?></span>
                                </td>
                                <td>
                                    <div class="time-info">
                                        <span><span class="time-label">Votes:</span>
                                            <strong><?php echo $row['total_votes']; ?></strong></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="time-info">
                                        <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                                        <div style="margin-top: 0.5rem;">
                                            <span><span class="time-label">Reg:</span>
                                                <?php echo $row['registration_status'] === 'open' ? '🟢 Open' : '🔴 Closed'; ?></span>
                                            <span><span class="time-label">Vote:</span>
                                                <?php echo $row['voting_status'] === 'active' ? '🟢 Active' : ($row['voting_status'] === 'ended' ? '⏹️ Ended' : '⏳ Not Started'); ?></span>
                                            <span><span class="time-label">Nomination:</span>
                                                <?php echo $row['nomination_status'] === 'open' ? '🟢 Open' : ($row['nomination_status'] === 'closed' ? '🔴 Closed' : '⏳ Not Started'); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="time-info" style="font-size: 0.75rem;">
                                        <span><span class="time-label">Participate Reg:</span>
                                            <?php echo date('M d, H:i', strtotime($row['registration_start'])); ?> -
                                            <?php echo date('M d, H:i', strtotime($row['registration_end'])); ?></span>
                                        <span><span class="time-label">Nomination Cancellation:</span>
                                            <?php echo date('M d, H:i', strtotime($row['nomination_cancellation_start'])); ?> -
                                            <?php echo date('M d, H:i', strtotime($row['nomination_cancellation_end'])); ?></span>
                                        <span><span class="time-label">Election Dates:</span>
                                            <?php echo date('M d, H:i', strtotime($row['election_start'])); ?> -
                                            <?php echo date('M d, H:i', strtotime($row['election_end'])); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <!-- Registration Toggle -->
                                        <div
                                            style="display: flex; align-items: center; justify-content: space-between; background: #F3F4F6; padding: 0.4rem 0.6rem; border-radius: 8px; min-width: 140px;">
                                            <span style="font-size: 0.7rem; font-weight: 700;">REG</span>
                                            <?php if ($row['registration_status'] === 'closed'): ?>
                                                <?php if ($row['status'] === 'Registration Ended' || $row['registration_ended'] == 1): ?>
                                                    <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">Closed</span>
                                                <?php else: ?>
                                                    <a href="update_status.php?id=<?php echo $row['id']; ?>&type=reg&value=open"
                                                        class="btn btn-primary"
                                                        style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; background: #10B981;">Start</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=reg&value=closed"
                                                    class="btn btn-danger"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; border-color: #EF4444;">End</a>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Nomination Toggle -->
                                        <div
                                            style="display: flex; align-items: center; justify-content: space-between; background: #F3F4F6; padding: 0.4rem 0.6rem; border-radius: 8px; min-width: 140px;">
                                            <span style="font-size: 0.7rem; font-weight: 700;">NOMINATION</span>
                                            <?php if ($row['nomination_ended'] == 1): ?>
                                                <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">Closed</span>
                                            <?php elseif ($row['nomination_status'] === 'not_started'): ?>
                                                <?php if ($row['registration_ended'] == 0): ?>
                                                    <span class="btn btn-secondary"
                                                        style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; opacity: 0.5; cursor: not-allowed; background: #E5E7EB;"
                                                        title="End Registration phase first">Start</span>
                                                <?php else: ?>
                                                    <?php if ($row['approved_candidates'] > 0): ?>
                                                        <a href="update_status.php?id=<?php echo $row['id']; ?>&type=nomination&value=open"
                                                            class="btn btn-primary"
                                                            style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; background: #10B981;">Start</a>
                                                    <?php else: ?>
                                                        <span class="btn btn-secondary"
                                                            style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; opacity: 0.5; cursor: not-allowed; background: #E5E7EB;"
                                                            title="No approved participants to nominate">Start</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php elseif ($row['nomination_status'] === 'open'): ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=nomination&value=closed"
                                                    class="btn btn-danger"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; border-color: #EF4444;">End</a>
                                            <?php else: ?>
                                                <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">Closed</span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Voting Toggle -->
                                        <div
                                            style="display: flex; align-items: center; justify-content: space-between; background: #F3F4F6; padding: 0.4rem 0.6rem; border-radius: 8px; min-width: 140px;">
                                            <span style="font-size: 0.7rem; font-weight: 700;">VOTE</span>
                                            <?php if ($row['voting_ended'] == 1): ?>
                                                <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">Ended</span>
                                            <?php elseif ($row['voting_status'] === 'not_started'): ?>
                                                <?php if ($row['nomination_ended'] == 0): ?>
                                                    <span class="btn btn-secondary"
                                                        style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; opacity: 0.5; cursor: not-allowed; background: #E5E7EB;"
                                                        title="End Nomination phase first">Start</span>
                                                <?php else: ?>
                                                    <?php if ($row['approved_candidates'] > 0): ?>
                                                        <a href="update_status.php?id=<?php echo $row['id']; ?>&type=vote&value=active"
                                                            class="btn btn-primary"
                                                            style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; background: #10B981;">Start</a>
                                                    <?php else: ?>
                                                        <span class="btn btn-secondary"
                                                            style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; opacity: 0.5; cursor: not-allowed; background: #E5E7EB;"
                                                            title="No approved candidates to vote for">Start</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php elseif ($row['voting_status'] === 'active'): ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=vote&value=ended"
                                                    class="btn btn-danger"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; border-color: #EF4444;">End</a>
                                            <?php else: ?>
                                                <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">Ended</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Publish Result Toggle -->
                                        <div
                                            style="display: flex; align-items: center; justify-content: space-between; background: #ECFDF5; padding: 0.4rem 0.6rem; border-radius: 8px; min-width: 140px; margin-top: 0.5rem;">
                                            <span style="font-size: 0.7rem; font-weight: 700; color: #059669;">PUBLISH</span>
                                            <?php if ($row['is_published'] == 1): ?>
                                                <span style="font-size: 0.7rem; color: #059669; font-weight: 600;">🚀 Published</span>
                                            <?php elseif ($row['voting_status'] === 'ended'): ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=publish&value=1"
                                                    class="btn btn-primary"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; background: #10B981; border: none;"
                                                    onclick="return confirm('Note: Once published, results will be visible to students. Continue?')">Publish</a>
                                            <?php else: ?>
                                                <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">⏳ Wait</span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Close Election Toggle -->
                                        <div
                                            style="display: flex; align-items: center; justify-content: space-between; background: #FEE2E2; padding: 0.4rem 0.6rem; border-radius: 8px; min-width: 140px; margin-top: 0.5rem;">
                                            <span style="font-size: 0.7rem; font-weight: 700; color: #DC2626;">CLOSE</span>
                                            <?php if ($row['status'] === 'Closed'): ?>
                                                <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">Closed</span>
                                            <?php elseif ($row['is_published'] == 1): ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=close&value=1"
                                                    class="btn btn-danger"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; border-color: #EF4444; background: #DC2626; color: white;">Close Now</a>
                                            <?php else: ?>
                                                <span class="btn btn-secondary"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; opacity: 0.5; cursor: not-allowed; background: #E5E7EB;"
                                                    title="Publish results first before closing">Close</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($row['voting_status'] === 'not_started'): ?>
                                            <a href="edit_election.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary"
                                                style="padding: 0.4rem;" title="Edit">✏️</a>
                                        <?php endif; ?>
                                        <a href="view_participants.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary"
                                            style="padding: 0.4rem;" title="Participants">👥</a>
                                        <a href="delete_election.php?id=<?php echo $row['id']; ?>" class="btn btn-danger"
                                            style="padding: 0.4rem; border: none; background: transparent; color: #EF4444;"
                                            title="Delete" onclick="return confirm('Archive this election?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No elections found. Start by creating one!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>