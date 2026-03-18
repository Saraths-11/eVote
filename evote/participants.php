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
$stmt = $conn->prepare("SELECT title FROM elections WHERE id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

if (!$election) {
    die("Election not found.");
}

// Fetch current user's participation status
$stmt = $conn->prepare("SELECT status, rejection_reason, cancellation_reason FROM participants WHERE election_id = ? AND user_id = ?");
$stmt->bind_param("ii", $election_id, $user_id);
$stmt->execute();
$my_status = $stmt->get_result()->fetch_assoc();

// Fetch all approved participants
$stmt = $conn->prepare("SELECT name, department, year, photo_path FROM participants WHERE election_id = ? AND status = 'Approved'");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$approved_participants = $stmt->get_result();
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
            padding: 2rem;
            border-radius: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .p-card:hover {
            transform: translateY(-5px);
        }

        .p-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 4px solid #f8fafc;
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
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Participants</div>
        <div class="nav-menu">
            <a href="enroll.php?id=<?php echo $election_id; ?>" class="btn btn-secondary">Back</a>
        </div>
    </nav>

    <div class="container">
        <div style="margin: 3rem 0;">
            <h1 style="font-size: 2.25rem; color: var(--dark);">
                <?php echo htmlspecialchars($election['title']); ?>
            </h1>
            <p style="color: #64748b;">List of approved participants contesting in this election.</p>
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
            <?php elseif ($my_status['status'] === 'Cancelled'): ?>
                <div class="status-alert alert-cancelled">
                    <span style="font-size: 2rem;">⚠️</span>
                    <div>
                        <h4 style="margin-bottom: 0.25rem;">Your participation was Cancelled</h4>
                        <p style="font-size: 0.9rem; opacity: 0.9;">
                            <?php echo !empty($my_status['cancellation_reason']) ? 'Reason: ' . htmlspecialchars($my_status['cancellation_reason']) : 'Due to college disciplinary action.'; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="p-grid">
            <?php if ($approved_participants->num_rows > 0): ?>
                <?php while ($row = $approved_participants->fetch_assoc()): ?>
                    <div class="p-card">
                        <img src="<?php echo htmlspecialchars($row['photo_path']); ?>" class="p-photo">
                        <h3 style="margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($row['name']); ?>
                        </h3>
                        <p style="color: #64748b; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($row['department']); ?> |
                            <?php echo htmlspecialchars($row['year']); ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div
                    style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: #f8fafc; border-radius: 1.5rem; color: #94a3b8;">
                    No approved participants for this election yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>