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

// Fetch Election Details
$stmt = $conn->prepare("SELECT title, voting_status, registration_status, nomination_status, show_voters FROM elections WHERE id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

if (!$election) {
    die("Election not found.");
}

// Auto-fix: Ensure removal columns exist in participants table
$res = $conn->query("SHOW COLUMNS FROM participants LIKE 'removal_reason'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE participants ADD COLUMN removal_reason TEXT DEFAULT NULL");
}
$res = $conn->query("SHOW COLUMNS FROM participants LIKE 'removed_at'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE participants ADD COLUMN removed_at DATETIME DEFAULT NULL");
}
$res = $conn->query("SHOW COLUMNS FROM participants LIKE 'removed_at'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE participants ADD COLUMN removed_at DATETIME DEFAULT NULL");
}

// Final Access Check: Only students can see this page (already handled at top)
// Removing individual participant status check to allow all students to see the candidates.

// Fetch all officially approved candidates
// NOTE: Students who have cancelled their participation will have status = 'Cancelled' 
// and will be automatically excluded from this list.
$stmt = $conn->prepare("SELECT id, name, college_id, department, year, photo_path, status, position FROM participants WHERE election_id = ? AND status = 'Approved' ORDER BY name ASC");

$stmt->bind_param("i", $election_id);
$stmt->execute();
$all_participants = $stmt->get_result();

// Fetch voters if allowed
$voters = [];
if ($election['show_voters'] == 1) {
    $v_stmt = $conn->prepare("SELECT u.accountFullName as name, u.college_id, u.department FROM votes v JOIN users u ON v.student_id = u.id WHERE v.election_id = ? ORDER BY v.voted_at DESC");
    $v_stmt->bind_param("i", $election_id);
    $v_stmt->execute();
    $voters = $v_stmt->get_result();
}

// Fetch MY status
$m_stmt = $conn->prepare("SELECT status, rejection_reason, removal_reason FROM participants WHERE election_id = ? AND user_id = ?");
$m_stmt->bind_param("ii", $election_id, $user_id);
$m_stmt->execute();
$my_status = $m_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Participants -
        <?php echo htmlspecialchars($election['title']); ?>
    </title>
    <link rel="stylesheet" href="style.css">
    <style>
        .p-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .p-card {
            background: white;
            padding: 2.25rem;
            border-radius: 2rem;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            border: 1px solid #f1f5f9;
        }

        .p-card:hover {
            transform: scale(1.05) translateY(-12px);
            box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.15);
            border-color: #e0e7ff;
        }

        .p-photo {
            width: 150px;
            height: 150px;
            border-radius: 1.5rem;
            /* slightly more rounded square */
            object-fit: cover;
            margin-bottom: 1.5rem;
            border: 4px solid #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .p-card:hover .p-photo {
            transform: scale(1.05);
            /* subtle extra zoom on photo inside card */
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .status-alert {
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .alert-rejected {
            background: #fff1f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-cancelled {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #ffedd5;
        }

        .alert-removed {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FEE2E2;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Participants</div>
        <div class="nav-menu">
            <a href="student_dashboard.php" class="btn btn-secondary">Back</a>
        </div>
    </nav>

    <div class="container">
        <div style="margin: 3rem 0;">
            <h1 style="font-size: 2.25rem; color: var(--dark);">
                Officially Participating Candidates
            </h1>
            <p style="color: #64748b;">List of students officially approved to participate in
                <?php echo htmlspecialchars($election['title']); ?>.
            </p>

            <?php
            // Requirement: Additionally, show total vote count (based on election settings)
            // For now, if voting is active or ended, we can show the total count.
            if ($election['voting_status'] !== 'not_started'):
                $v_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM votes WHERE election_id = ?");
                $v_count_stmt->bind_param("i", $election_id);
                $v_count_stmt->execute();
                $total_votes_cast = $v_count_stmt->get_result()->fetch_assoc()['total'];
                ?>
                <div
                    style="margin-top: 1.5rem; background: #f8fafc; padding: 1rem; border-radius: 1rem; display: inline-block; border: 1px solid #e2e8f0; margin-right: 1rem;">
                    <span style="font-weight: 800; color: #4f46e5; font-size: 1.1rem;">📊 Total Votes Cast:
                        <?php echo $total_votes_cast; ?></span>
                </div>

                <?php if ($election['show_voters'] == 1): ?>
                    <div
                        style="margin-top: 1.5rem; background: #ecfdf5; padding: 1rem; border-radius: 1rem; display: inline-block; border: 1px solid #d1fae5;">
                        <span style="font-weight: 800; color: #065f46; font-size: 1.1rem;">👥 Voter Visibility: Enabled</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($my_status): ?>
            <?php if ($my_status['status'] === 'Rejected'): ?>
                <div class="status-alert alert-rejected">
                    <span style="font-size: 2rem;">❌</span>
                    <div>
                        <h4 style="margin-bottom: 0.25rem;">Your registration was Rejected</h4>
                        <p style="font-size: 0.9rem; opacity: 0.9;">
                            <?php echo !empty($my_status['rejection_reason']) ? 'Reason: ' . htmlspecialchars($my_status['rejection_reason']) : 'No specific reason provided by the admin.'; ?>
                        </p>
                    </div>
                </div>
            <?php elseif ($my_status['status'] === 'Removed by Admin'): ?>
                <div class="status-alert alert-removed">
                    <span style="font-size: 2rem;">🔴</span>
                    <div>
                        <h4 style="margin-bottom: 0.25rem;">Your participation was Removed</h4>
                        <p style="font-size: 0.9rem; opacity: 0.9;">
                            <?php echo !empty($my_status['removal_reason']) ? 'Reason: ' . htmlspecialchars($my_status['removal_reason']) : 'Due to administrative decision or disciplinary action.'; ?>
                        </p>
                    </div>
                </div>
            <?php elseif ($my_status['status'] === 'Cancelled'): ?>
                <div class="status-alert alert-cancelled">
                    <span style="font-size: 2rem;">⚠️</span>
                    <div>
                        <h4 style="margin-bottom: 0.25rem;">Your participation was Cancelled</h4>
                        <p style="font-size: 0.9rem; opacity: 0.9;">
                            <?php echo !empty($my_status['cancellation_reason']) ? 'Reason: ' . htmlspecialchars($my_status['cancellation_reason']) : 'Your participation has been withdrawn.'; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <div class="p-grid">
            <?php if ($all_participants->num_rows > 0): ?>
                <?php while ($row = $all_participants->fetch_assoc()): ?>
                    <div class="p-card">
                        <img src="<?php echo htmlspecialchars($row['photo_path']); ?>" class="p-photo">
                        <h3 style="margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </h3>
                        <div
                            style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; margin-bottom: 0.5rem;">
                            <span
                                style="font-size: 0.7rem; background: #fef3c7; color: #92400e; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700;">
                                DEPT: <?php echo htmlspecialchars($row['department']); ?>
                            </span>
                        </div>
                        <?php if (!empty($row['position'])): ?>
                            <p style="font-weight: 700; color: #4f46e5; margin-bottom: 0.5rem; font-size: 0.95rem;">
                                Contesting For: <?php echo htmlspecialchars($row['position']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                <?php endwhile; ?>
            <?php else: ?>
                <div
                    style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: #f8fafc; border-radius: 1.5rem; color: #94a3b8;">
                    No participants for this election yet.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($election['show_voters'] == 1): ?>
            <div style="margin-top: 5rem; margin-bottom: 5rem;">
                <div
                    style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 1rem;">
                    <h2 style="font-size: 1.875rem; color: var(--dark);">Voted Students List</h2>
                    <span
                        style="background: #ecfdf5; color: #065f46; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 700; font-size: 0.875rem;">
                        <?php echo $total_votes_cast; ?> Students Participated
                    </span>
                </div>

                <div
                    style="background: white; border-radius: 1.5rem; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th
                                    style="padding: 1.25rem 2rem; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                    Student Name</th>
                                <th
                                    style="padding: 1.25rem 2rem; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                    College ID</th>
                                <th
                                    style="padding: 1.25rem 2rem; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                    Department</th>
                                <th
                                    style="padding: 1.25rem 2rem; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($voters->num_rows > 0): ?>
                                <?php while ($v = $voters->fetch_assoc()): ?>
                                    <tr style="border-bottom: 1px solid #f8fafc;">
                                        <td style="padding: 1.25rem 2rem; font-weight: 600; color: #1e293b;">
                                            <?php echo htmlspecialchars($v['name']); ?>
                                        </td>
                                        <td style="padding: 1.25rem 2rem; color: #64748b; font-family: monospace;">
                                            <?php echo htmlspecialchars($v['college_id']); ?>
                                        </td>
                                        <td style="padding: 1.25rem 2rem; color: #64748b;">
                                            <?php echo htmlspecialchars($v['department']); ?>
                                        </td>
                                        <td style="padding: 1.25rem 2rem;">
                                            <span
                                                style="display: inline-flex; align-items: center; gap: 0.5rem; color: #10b981; font-weight: 700; font-size: 0.875rem;">
                                                <span
                                                    style="width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
                                                Voted
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="padding: 3rem; text-align: center; color: #94a3b8;">No votes recorded
                                        yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>