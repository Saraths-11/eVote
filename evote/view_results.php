<?php
session_start();
include 'config.php';

// Access Control: Admin, Faculty OR Students (if voting ended)
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$is_admin = ($role === 'admin');
$is_faculty = ($role === 'faculty');

// If student or faculty, check if we're viewing a specific election's results and if voting has ended
if (!$is_admin && !isset($_GET['id'])) {
    if ($is_faculty) {
        header("Location: faculty_dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit();
}

$election_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Fetch Elections list if no ID provided (Admin only can see list of all results)
if (!$election_id) {
    if (!$is_admin) {
        header("Location: login.php");
        exit();
    }
    $sql = "SELECT id, title, voting_status, eligible_voters, (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.id) as total_votes FROM elections e ORDER BY created_at DESC";
    $elections_res = $conn->query($sql);
} else {
    // Fetch specific election details
    $stmt = $conn->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->bind_param("i", $election_id);
    $stmt->execute();
    $election = $stmt->get_result()->fetch_assoc();

    if (!$election) {
        die("Election not found.");
    }

    // Access Control: Check if published
    if (!$is_admin && (!isset($election['is_published']) || !$election['is_published'])) {
        if ($is_faculty) {
            header("Location: faculty_dashboard.php");
        } else {
            header("Location: student_dashboard.php");
        }
        exit();
    }

    // Fetch candidates and results
    $sql_results = "SELECT c.*, (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) as vote_count FROM participants c WHERE c.election_id = ? AND c.status = 'Approved' ORDER BY vote_count DESC";
    $stmt_results = $conn->prepare($sql_results);
    $stmt_results->bind_param("i", $election_id);
    $stmt_results->execute();
    $results = $stmt_results->get_result();

    // Total votes for participation display
    $stmt_total = $conn->prepare("SELECT COUNT(*) as total FROM votes WHERE election_id = ?");
    $stmt_total->bind_param("i", $election_id);
    $stmt_total->execute();
    $total_data = $stmt_total->get_result()->fetch_assoc();
    $total_votes = $total_data['total'] ?: 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Election Results - eVote</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .result-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .candidate-result {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .candidate-result:hover {
            background: #f8fafc;
        }

        .candidate-result:last-child {
            border-bottom: none;
        }

        .progress-bar-bg {
            background: #f1f5f9;
            height: 12px;
            border-radius: 6px;
            flex: 1;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, var(--primary) 0%, #4f46e5 100%);
            height: 100%;
            transition: width 1s ease-out;
        }

        .winner-badge {
            background: #dcfce7;
            color: #166534;
            font-size: 0.7rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #bbf7d0;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .highlight-winner {
            background: #fffbeb;
            border: 2px solid #fcd34d;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Results</div>
        <div class="nav-menu">
            <?php
            $back_url = 'student_dashboard.php';
            if ($is_admin)
                $back_url = 'admin_dashboard.php';
            if ($is_faculty)
                $back_url = 'faculty_dashboard.php';
            ?>
            <a href="<?php echo $back_url; ?>" class="btn btn-secondary"
                style="padding: 0.5rem 1rem; font-size: 0.9rem;">Back</a>
            <a href="logout.php" class="btn btn-secondary"
                style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-left: 1rem;">Logout</a>
        </div>
    </nav>

    <div class="container" style="max-width: 900px;">
        <?php if (!$election_id): ?>
            <h2 style="margin: 2rem 0; color: var(--dark);">📊 Election Results Overview</h2>
            <div class="grid-cards">
                <?php while ($row = $elections_res->fetch_assoc()): ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.5rem;">Total Votes:
                            <strong><?php echo $row['total_votes']; ?></strong>
                        </p>
                        <div style="margin-top: 1.5rem;">
                            <a href="view_results.php?id=<?php echo $row['id']; ?>" class="btn btn-primary"
                                style="font-size: 0.85rem; padding: 0.5rem 1rem; width: 100%;">View Detailed Results</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin: 3rem 0;">
                <h1 style="font-size: 2.25rem; color: var(--dark); margin-bottom: 0.75rem;">
                    <?php echo htmlspecialchars($election['title']); ?>
                </h1>
                <span class="status-badge"
                    style="background: #f1f5f9; padding: 0.5rem 1.25rem; border-radius: 30px; font-size: 0.85rem; font-weight: 600; color: #475569;">
                    Election Status: <?php echo ucfirst(str_replace('_', ' ', $election['voting_status'])); ?>
                </span>
            </div>

            <div class="result-card">
                <h3
                    style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                    <span>Results Standings</span>
                    <div style="text-align: right;">
                        <span style="color: #64748b; font-weight: 500; font-size: 0.95rem;">Total Votes Cast:
                            <strong><?php echo $total_votes; ?></strong>
                            <span style="font-weight: normal; font-size: 0.85rem; margin-left: 0.5rem;">(out of
                                <?php echo $election['eligible_voters']; ?> eligible)</span>
                        </span>
                    </div>
                </h3>

                <?php
                $rank = 0;
                $max_votes = -1;
                $results_array = [];
                while ($row = $results->fetch_assoc()) {
                    $results_array[] = $row;
                    if ($row['vote_count'] > $max_votes)
                        $max_votes = $row['vote_count'];
                }

                $role = $_SESSION['role'] ?? '';
                $is_student = ($role === 'student');

                foreach ($results_array as $cand):
                    $rank++;

                    // FOR STUDENTS: Only show the Winner (Rank 1). Hide everyone else.
                    if ($is_student && $rank > 1) {
                        continue;
                    }

                    $eligible_voters = $election['eligible_voters'];
                    // Calculate percentage based on ELIGIBLE VOTERS, not total votes cast
                    $percent = ($eligible_voters > 0) ? round(($cand['vote_count'] / $eligible_voters) * 100, 2) : 0;
                    $percent = min(100, $percent);

                    $is_winner = ($rank === 1 && $cand['vote_count'] > 0 && $election['voting_status'] === 'ended');
                    $is_leading = ($rank === 1 && $cand['vote_count'] > 0 && $election['voting_status'] !== 'ended');
                    ?>
                    <div class="candidate-result <?php echo ($is_winner) ? 'highlight-winner' : ''; ?>">
                        <?php if ($is_student): ?>
                            <!-- Simplified View for Students -->
                            <div style="flex: 1; text-align: center;">
                                <div
                                    style="font-size: 0.9rem; color: #64748b; font-weight: 700; margin-bottom: 0.5rem; text-transform: uppercase;">
                                    Final Winner</div>
                                <img src="<?php echo htmlspecialchars($cand['photo_path']); ?>"
                                    style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #dcfce7; margin-bottom: 1rem;">
                                <h2 style="margin: 0; color: #1e293b;"><?php echo htmlspecialchars($cand['name']); ?></h2>
                                <p style="color: #64748b; margin-top: 0.5rem; font-weight: 500;">
                                    Recieved <strong><?php echo $cand['vote_count']; ?></strong> votes
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Full Admin/Faculty View -->
                            <img src="<?php echo htmlspecialchars($cand['photo_path']); ?>"
                                style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <strong
                                            style="font-size: 1.1rem; color: #1e293b;"><?php echo htmlspecialchars($cand['name']); ?></strong>
                                        <?php if ($is_winner): ?>
                                            <span class="winner-badge">🏆 Winner</span>
                                        <?php elseif ($is_leading): ?>
                                            <span class="winner-badge"
                                                style="background: #e0f2fe; color: #0369a1; border-color: #bae6fd;">📈 Leading</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <span
                                            style="font-size: 1.1rem; font-weight: 800; color: var(--primary);"><?php echo $cand['vote_count']; ?></span>
                                        <span style="font-size: 0.85rem; color: #64748b; margin-left: 0.25rem;">votes
                                            (<?php echo $percent; ?>%)</span>
                                    </div>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 0.8rem; color: #94a3b8; font-weight: 500;">
                                        <?php echo htmlspecialchars($cand['department']); ?> | Year <?php echo $cand['year']; ?>
                                    </span>
                                    <?php if ($rank <= 3): ?>
                                        <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8;">RANK
                                            #<?php echo $rank; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($results_array)): ?>
                    <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                        <p>No approved candidates found for this election.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($is_admin): ?>
                <div
                    style="text-align: center; margin-top: 2.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">

                    <?php if (isset($election['is_published']) && $election['is_published']): ?>
                        <a href="update_status.php?id=<?php echo $election['id']; ?>&type=publish&value=0&redirect=view_results"
                            class="btn btn-secondary" style="border: 1px solid #ef4444; background: #fee2e2; color: #b91c1c;"
                            onclick="return confirm('Are you sure you want to UNPUBLISH these results? Students will no longer see them.')">
                            🚫 Unpublish Results
                        </a>
                    <?php else: ?>
                        <a href="update_status.php?id=<?php echo $election['id']; ?>&type=publish&value=1&redirect=view_results"
                            class="btn btn-primary" style="background: #10b981; border-color: #059669;"
                            onclick="return confirm('Are you sure you want to PUBLISH these results? They will become visible to all students.')">
                            📢 Publish Results
                        </a>
                    <?php endif; ?>

                    <button onclick="window.print()" class="btn btn-secondary"
                        style="border: 1px solid #e2e8f0; background: white; color: #475569;">🖨️ Print Official Report</button>
                    <a href="admin_dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>

</html>