<?php
session_start();
include 'config.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = $_GET['msg'] ?? '';

// Auto-fix: Add is_published column if missing
$check_col = $conn->query("SHOW COLUMNS FROM elections LIKE 'is_published'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE elections ADD COLUMN is_published TINYINT(1) DEFAULT 0");
}

// Fetch Elections
$sql = "SELECT e.*, (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.id) as total_votes FROM elections e ORDER BY created_at DESC";
$result = $conn->query($sql);
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
            /* Fixed: Added display block for truncation */
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

        <div class="page-header">
            <h1>Election Management</h1>
            <a href="create_election.php" class="btn btn-primary">+ Create Election</a>
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
                            if ($status == 'Voting Active')
                                $badgeClass = 'status-active';
                            if ($status == 'Completed')
                                $badgeClass = 'status-completed';
                            if ($status == 'Registration Ended')
                                $badgeClass = 'status-completed'; // Using same style
                    
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
                                        <span><span class="time-label">Eligible:</span>
                                            <strong><?php echo $row['eligible_voters']; ?></strong></span>
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
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="time-info" style="font-size: 0.75rem;">
                                        <span><span class="time-label">Reg:</span>
                                            <?php echo date('M d, H:i', strtotime($row['registration_start'])); ?> -
                                            <?php echo date('M d, H:i', strtotime($row['registration_end'])); ?></span>
                                        <span><span class="time-label">Vote:</span>
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
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=reg&value=open"
                                                    class="btn btn-primary"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; background: #10B981;">Start</a>
                                            <?php else: ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=reg&value=closed"
                                                    class="btn btn-danger"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; border-color: #EF4444;">End</a>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Voting Toggle -->
                                        <div
                                            style="display: flex; align-items: center; justify-content: space-between; background: #F3F4F6; padding: 0.4rem 0.6rem; border-radius: 8px; min-width: 140px;">
                                            <span style="font-size: 0.7rem; font-weight: 700;">VOTE</span>
                                            <?php if ($row['voting_status'] === 'not_started'): ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=vote&value=active"
                                                    class="btn btn-primary"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; background: #10B981;">Start</a>
                                            <?php elseif ($row['voting_status'] === 'active'): ?>
                                                <a href="update_status.php?id=<?php echo $row['id']; ?>&type=vote&value=ended"
                                                    class="btn btn-danger"
                                                    style="padding: 0.2rem 0.6rem; font-size: 0.7rem; border-radius: 4px; border-color: #EF4444;">End</a>
                                            <?php else: ?>
                                                <span style="font-size: 0.7rem; color: #9CA3AF; font-weight: 600;">Ended</span>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="edit_election.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary"
                                            style="padding: 0.4rem;" title="Edit">✏️</a>
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