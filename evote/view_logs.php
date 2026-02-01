<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include 'config.php';

$sql = "SELECT l.*, u.name as user_name, e.title as election_title 
        FROM voting_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        LEFT JOIN elections e ON l.election_id = e.id 
        ORDER BY l.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Audit Logs - eVote</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .log-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .log-table th,
        .log-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        .log-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .status-success {
            color: #10b981;
            font-weight: 700;
        }

        .status-failed {
            color: #ef4444;
            font-weight: 700;
        }

        .status-error {
            color: #f59e0b;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Audit</div>
        <div class="nav-menu">
            <a href="admin_dashboard.php" class="btn btn-secondary">Back</a>
        </div>
    </nav>

    <div class="container" style="max-width: 1100px;">
        <h2 style="margin: 2rem 0; color: var(--dark);">📜 System Audit Logs</h2>

        <table class="log-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Election</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-size: 0.85rem; color: #64748b;">
                                <?php echo date('M d, H:i:s', strtotime($row['created_at'])); ?>
                            </td>
                            <td><strong>
                                    <?php echo htmlspecialchars($row['user_name'] ?: 'Unknown'); ?>
                                </strong></td>
                            <td style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars($row['election_title'] ?: 'N/A'); ?>
                            </td>
                            <td style="font-size: 0.85rem; font-weight: 600;">
                                <?php echo strtoupper(str_replace('_', ' ', $row['action'])); ?>
                            </td>
                            <td>
                                <span class="status-<?php echo $row['status']; ?>">
                                    <?php echo strtoupper($row['status']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: #64748b;">
                                <?php echo htmlspecialchars($row['ip_address']); ?>
                            </td>
                            <td style="font-size: 0.85rem; color: #475569;">
                                <?php echo htmlspecialchars($row['details']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: #94a3b8;">No logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>