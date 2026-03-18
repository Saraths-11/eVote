<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include 'config.php';

// Fetch stats for the dashboard
$total_elections = $conn->query("SELECT COUNT(*) as count FROM elections")->fetch_assoc()['count'];
$active_elections = $conn->query("SELECT COUNT(*) as count FROM elections WHERE voting_status = 'active'")->fetch_assoc()['count'];
$pending_participants = $conn->query("SELECT COUNT(*) as count FROM participants WHERE status = 'Pending'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - eVote</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stat-card .label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .stat-card .value {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-card .trend {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #fef2f2;
            color: #dc2626;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Admin</div>
        <div class="nav-menu">
            <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></span>
            <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Logout</a>
        </div>
    </nav>

    <div class="dashboard-content">
        <div class="welcome-banner">
            <h2>Admin Dashboard</h2>
            <p>Securely manage college elections, verify participants, and monitor results in real-time.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="label">Total Elections</span>
                <span class="value"><?php echo $total_elections; ?></span>
                <span class="trend" style="color: #64748b;">System total</span>
            </div>
            <div class="stat-card">
                <span class="label">Active Voting</span>
                <span class="value"><?php echo $active_elections; ?></span>
                <?php if ($active_elections > 0): ?>
                    <span class="live-indicator">● LIVE NOW</span>
                <?php else: ?>
                    <span class="trend" style="color: #64748b;">No active polls</span>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <span class="label">Pending Review</span>
                <span class="value"><?php echo $pending_participants; ?></span>
                <a href="view_participants.php"
                    style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 600;">Action
                    Required →</a>
            </div>
        </div>

        <?php
        $sql = "SELECT e.*, (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.id) as total_votes FROM elections e ORDER BY created_at DESC LIMIT 5";
        $result = $conn->query($sql);
        ?>

        <div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;">📅 Recent Elections</h3>
                <a href="manage_elections.php" style="font-size: 0.9rem; color: var(--primary); font-weight: 600;">View
                    All →</a>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #f3f4f6;">
                            <th style="padding: 0.75rem; font-size: 0.85rem; color: var(--gray);">Title</th>
                            <th style="padding: 0.75rem; font-size: 0.85rem; color: var(--gray);">Voting Status</th>
                            <th style="padding: 0.75rem; font-size: 0.85rem; color: var(--gray);">Participation</th>
                            <th style="padding: 0.75rem; font-size: 0.85rem; color: var(--gray);">Start Date</th>
                            <th style="padding: 0.75rem; font-size: 0.85rem; color: var(--gray);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 0.75rem; font-weight: 500;">
                                        <?php echo htmlspecialchars($row['title']); ?>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <?php
                                        $vStatus = $row['voting_status'];
                                        $statusColor = '#6b7280';
                                        if ($vStatus == 'active')
                                            $statusColor = '#10b981';
                                        if ($vStatus == 'ended')
                                            $statusColor = '#ef4444';
                                        if ($vStatus == 'not_started')
                                            $statusColor = '#f59e0b';
                                        ?>
                                        <span
                                            style="font-size: 0.75rem; font-weight: 700; color: <?php echo $statusColor; ?>; background: <?php echo $statusColor; ?>15; padding: 0.25rem 0.6rem; border-radius: 20px; text-transform: uppercase;">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $vStatus)); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.85rem; color: #64748b;">
                                        <strong><?php echo $row['total_votes']; ?></strong> Votes
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.85rem; color: var(--gray);">
                                        <?php echo date('M d, Y', strtotime($row['election_start'])); ?>
                                    </td>
                                    <td style="padding: 0.75rem; display: flex; gap: 1rem;">
                                        <a href="edit_election.php?id=<?php echo $row['id']; ?>"
                                            style="color: var(--primary); font-size: 0.85rem; text-decoration: none; font-weight: 600;">Edit</a>
                                        <a href="view_results.php?id=<?php echo $row['id']; ?>"
                                            style="color: #10b981; font-size: 0.85rem; text-decoration: none; font-weight: 600;">Results</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="padding: 2rem; text-align: center; color: var(--gray);">No elections
                                    found. <a href="create_election.php" style="color: var(--primary);">Create one now</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h3 style="margin-bottom: 1.5rem; color: var(--dark);">🚀 Quick Management</h3>

        <div class="grid-cards">
            <div class="card">
                <div style="font-size: 2rem; margin-bottom: 1rem;">🗳️</div>
                <h3>Manage Elections</h3>
                <p>Create, update, or delete elections and set voting windows.</p>
                <div style="margin-top: 1.5rem;">
                    <a href="create_election.php" class="btn btn-primary"
                        style="padding: 0.6rem 1.25rem; font-size: 0.9rem;">Create New</a>
                    <a href="manage_elections.php" class="btn btn-secondary"
                        style="padding: 0.6rem 1.25rem; font-size: 0.9rem; margin-left: 0.5rem;">Manage All</a>
                </div>
            </div>

            <div class="card">
                <div style="font-size: 2rem; margin-bottom: 1rem;">👥</div>
                <h3>Participants</h3>
                <p>Review and approve student registration requests for elections.</p>
                <div style="margin-top: 1.5rem;">
                    <a href="view_participants.php" class="btn btn-primary"
                        style="padding: 0.6rem 1.25rem; font-size: 0.9rem;">Verify Users</a>
                </div>
            </div>

            <div class="card" style="border: 2px solid #dcfce7;">
                <div style="font-size: 2rem; margin-bottom: 1rem;">📊</div>
                <h3>Election Results</h3>
                <p>View final standings, winners, and detailed vote counts.</p>
                <div style="margin-top: 1.5rem;">
                    <a href="view_results.php" class="btn btn-primary"
                        style="padding: 0.6rem 1.25rem; font-size: 0.9rem; background: #10b981;">View Results</a>
                </div>
            </div>

            <div class="card">
                <div style="font-size: 2rem; margin-bottom: 1rem;">📜</div>
                <h3>Audit Logs</h3>
                <p>Monitor system activity and voting logs for transparency.</p>
                <div style="margin-top: 1.5rem;">
                    <a href="view_logs.php" class="btn btn-secondary"
                        style="padding: 0.6rem 1.25rem; font-size: 0.9rem;">View Logs</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>