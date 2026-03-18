<?php
session_start();
include 'config.php';

// Access Control: Student Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: student_dashboard.php");
    exit();
}

$election_id = intval($_GET['id']);

// Fetch Election Details
$stmt = $conn->prepare("SELECT * FROM elections WHERE id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$result = $stmt->get_result();
$election = $result->fetch_assoc();

if (!$election) {
    die("Election not found.");
}

// AUTO-FIX: Ensure new columns exist
if (!isset($election['registration_status'])) {
    $conn->query("ALTER TABLE elections ADD COLUMN registration_status ENUM('open', 'closed') DEFAULT 'closed' AFTER status");
    $conn->query("ALTER TABLE elections ADD COLUMN voting_status ENUM('not_started', 'active', 'ended') DEFAULT 'not_started' AFTER registration_status");
    // Refetch election to get new columns
    $stmt->execute();
    $election = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($election['title']); ?> - Details
    </title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --secondary-gradient: linear-gradient(135deg, #f472b6 0%, #db2777 100%);
            --accent-gradient: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            --bg-soft: #f8fafc;
            --glass: rgba(255, 255, 255, 0.95);
        }

        body {
            background-color: var(--bg-soft);
            background-image:
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(219, 39, 119, 0.05) 0px, transparent 50%);
            font-family: 'Outfit', 'Inter', sans-serif;
        }

        .details-wrapper {
            max-width: 900px;
            margin: 3rem auto;
            background: var(--glass);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .details-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--primary-gradient);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1.25rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.1);
        }

        .election-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 3rem;
        }

        .info-card {
            background: #ffffff;
            padding: 2rem;
            border-radius: 1.5rem;
            border: 1px solid #f1f5f9;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .info-card h4 {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .date-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed #e2e8f0;
        }

        .date-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .date-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .date-value {
            color: #1e293b;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .description-box {
            margin: 2rem 0;
            line-height: 1.8;
            color: #475569;
            font-size: 1.1rem;
        }

        .action-btns {
            display: flex;
            gap: 1.5rem;
            margin-top: 3.5rem;
        }

        .btn-modern {
            padding: 1.25rem 2.5rem;
            border-radius: 1.25rem;
            font-weight: 700;
            font-size: 1.05rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            border: none;
            cursor: pointer;
        }

        .btn-participants {
            background: #f1f5f9;
            color: #475569;
            flex: 1;
        }

        .btn-participants:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: scale(1.02);
        }

        .btn-action {
            flex: 1;
            background: var(--secondary-gradient);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(219, 39, 119, 0.3);
        }

        .btn-action:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 25px -5px rgba(219, 39, 119, 0.4);
        }

        .btn-action:active {
            transform: translateY(1px);
        }

        .btn-disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            flex: 1;
        }

        @media (max-width: 768px) {
            .election-info-grid {
                grid-template-columns: 1fr;
            }

            .action-btns {
                flex-direction: column;
            }

            .btn-participants {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Student</div>
        <div class="nav-menu">
            <a href="student_dashboard.php" class="btn btn-secondary"
                style="padding: 0.5rem 1rem; font-size: 0.9rem;">Back</a>
            <a href="logout.php" class="btn btn-secondary"
                style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-left: 1rem;">Logout</a>
        </div>
    </nav>

    <div class="container" style="padding: 2rem 1rem;">
        <div class="details-wrapper">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1.5rem;">
                <h2 style="font-size: 2.5rem; color: #1e293b; font-weight: 800; letter-spacing: -0.02em; margin: 0;">
                    <?php echo htmlspecialchars($election['title']); ?>
                </h2>
                <?php
                $statusColor = '#64748b';
                if ($election['status'] == 'Voting Active')
                    $statusColor = '#10b981';
                if ($election['status'] == 'Registration Open')
                    $statusColor = '#3b82f6';
                if ($election['status'] == 'Upcoming')
                    $statusColor = '#f59e0b';
                ?>
                <span class="status-pill"
                    style="color: <?php echo $statusColor; ?>; background: <?php echo $statusColor; ?>15;">
                    <span
                        style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $statusColor; ?>; margin-right: 8px; display: inline-block;"></span>
                    <?php echo htmlspecialchars($election['status']); ?>
                </span>
            </div>

            <div class="description-box">
                <?php echo nl2br(htmlspecialchars($election['description'])); ?>
            </div>

            <div class="election-info-grid">
                <div class="info-card">
                    <h4><span>🗓️</span> Election Schedule</h4>
                    <div class="date-row">
                        <span class="date-label">Starts</span>
                        <span
                            class="date-value"><?php echo date('M d, Y h:i A', strtotime($election['election_start'])); ?></span>
                    </div>
                    <div class="date-row">
                        <span class="date-label">Ends</span>
                        <span
                            class="date-value"><?php echo date('M d, Y h:i A', strtotime($election['election_end'])); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h4><span>👥</span> Registration Period</h4>
                    <div class="date-row">
                        <span class="date-label">Starts</span>
                        <span
                            class="date-value"><?php echo date('M d, Y h:i A', strtotime($election['registration_start'])); ?></span>
                    </div>
                    <div class="date-row">
                        <span class="date-label">Ends</span>
                        <span
                            class="date-value"><?php echo date('M d, Y h:i A', strtotime($election['registration_end'])); ?></span>
                    </div>
                </div>
            </div>

            <?php
            $user_id = $_SESSION['user_id'];
            $part_stmt = $conn->prepare("SELECT status, cancellation_reason, rejection_reason FROM participants WHERE election_id = ? AND user_id = ?");
            $participation = null;
            if ($part_stmt) {
                $part_stmt->bind_param("ii", $election_id, $user_id);
                $part_stmt->execute();
                $participation = $part_stmt->get_result()->fetch_assoc();
            }

            $now = time();
            $reg_start = strtotime($election['registration_start']);
            $reg_end = strtotime($election['registration_end']);
            $is_reg_open = ($now >= $reg_start && $now <= $reg_end);
            ?>

            <?php
            // Ensure votes table exists to prevent prepare() failure
            $conn->query("CREATE TABLE IF NOT EXISTS votes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                election_id INT NOT NULL,
                student_id INT NOT NULL,
                candidate_id INT NOT NULL,
                college_id VARCHAR(50),
                voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (election_id, student_id)
            )");

            // Check if user has already voted
            $check_vote = $conn->prepare("SELECT id FROM votes WHERE election_id = ? AND student_id = ?");
            if ($check_vote) {
                $check_vote->bind_param("ii", $election_id, $user_id);
                $check_vote->execute();
                $has_voted = $check_vote->get_result()->fetch_assoc();
            } else {
                $has_voted = false;
            }
            ?>

            <div class="action-btns">
                <?php if ($election['voting_status'] !== 'active'): ?>
                    <a href="participants.php?id=<?php echo $election_id; ?>" class="btn-modern btn-participants">
                        <span>👥</span> View Participants
                    </a>
                <?php endif; ?>

                <?php if ($election['voting_status'] == 'active'): ?>
                    <?php if ($has_voted): ?>
                        <div class="btn-modern btn-disabled"
                            style="background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5;">
                            <span>✅</span> Vote Submitted
                        </div>
                    <?php else: ?>
                        <a href="vote.php?id=<?php echo $election_id; ?>" class="btn-modern btn-action"
                            style="background: var(--primary-gradient);">
                            <span>🗳️</span> Cast Your Vote Now
                        </a>
                    <?php endif; ?>
                <?php elseif ($election['voting_status'] == 'ended'): ?>
                    <div class="btn-modern btn-disabled">
                        <span>🏁</span> Voting Period Ended
                    </div>
                <?php else: ?>
                    <?php if ($participation): ?>
                        <div class="btn-modern btn-disabled">
                            <span>📋</span> Registration <?php echo $participation['status']; ?>
                        </div>
                    <?php elseif ($election['registration_status'] == 'open'): ?>
                        <a href="register_participant.php?id=<?php echo $election_id; ?>" class="btn-modern btn-action">
                            <span>✨</span> Participate Now
                        </a>
                    <?php else: ?>
                        <div class="btn-modern btn-disabled">
                            <span>🔒</span> Registration Not Open
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($participation && ($participation['status'] == 'Cancelled' || $participation['status'] == 'Rejected')): ?>
                <div
                    style="background: #fff; border-left: 5px solid #ef4444; padding: 2rem; border-radius: 1rem; margin-top: 2.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
                    <h4
                        style="margin: 0 0 1rem 0; color: #b91c1c; display: flex; align-items: center; gap: 0.75rem; font-size: 1.25rem;">
                        <?php echo $participation['status'] == 'Cancelled' ? '⚠️ Participation Revoked' : '❌ Registration Denied'; ?>
                    </h4>
                    <p style="color: #4b5563; font-size: 1rem; margin: 0;">
                        <?php echo $participation['status'] == 'Cancelled' ? "Your participation was cancelled by the administration." : "Your registration was not approved."; ?>
                    </p>
                    <?php $reason = $participation['status'] == 'Cancelled' ? $participation['cancellation_reason'] : ($participation['rejection_reason'] ?? '');
                    if (!empty($reason)): ?>
                        <div
                            style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #fee2e2; color: #7f1d1d; font-size: 0.95rem;">
                            <strong>Note:</strong> <?php echo htmlspecialchars($reason); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p style="text-align: center; margin-top: 3rem; font-size: 0.9rem; color: #94a3b8; font-weight: 500;">
                <span style="opacity: 0.6;">🔒 Secured by eVote AJCE Verification System</span>
            </p>
        </div>
    </div>
</body>

</html>