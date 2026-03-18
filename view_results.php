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
$is_student = ($role === 'student');

// Database Migration: Ensure result_view_type column exists
$conn->query("ALTER TABLE elections ADD COLUMN IF NOT EXISTS result_view_type ENUM('table', 'pie') DEFAULT 'pie'");

// Handle View Type Change (Admin only)
if ($is_admin && isset($_POST['change_view_type']) && isset($_POST['view_type']) && isset($_GET['id'])) {
    $new_type = $_POST['view_type'];
    $eid = intval($_GET['id']);
    $stmt_up = $conn->prepare("UPDATE elections SET result_view_type = ? WHERE id = ?");
    $stmt_up->bind_param("si", $new_type, $eid);
    $stmt_up->execute();
    header("Location: view_results.php?id=" . $eid . "&msg=view_updated");
    exit();
}

// REAL-TIME API FOR LIVE MONITORING
if (isset($_GET['ajax_live']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $eid = intval($_GET['id']);
    
    // Fast queries using indexes (if any)
    $sql_live = "SELECT c.id as participant_id, (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) as vote_count 
                 FROM participants c WHERE c.election_id = ? AND c.status = 'Approved'";
    $stmt_live = $conn->prepare($sql_live);
    $stmt_live->bind_param("i", $eid);
    $stmt_live->execute();
    $live_results = $stmt_live->get_result();
    
    $data = [];
    while ($row = $live_results->fetch_assoc()) {
        $data[$row['participant_id']] = $row['vote_count'];
    }
    echo json_encode(['success' => true, 'counts' => $data]);
    exit();
}

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

    $is_published = (isset($election['is_published']) && $election['is_published'] == 1);

    // Access Control: Admins see everything. Students are blocked from 'Closed' (Archived) elections unless results are published.
    if (!$is_admin && $election['status'] === 'Closed' && $is_student && !$is_published) {
        header("Location: student_dashboard.php?error=election_archived");
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background: #fffbeb !important;
            border: 2px solid #fcd34d !important;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .results-table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            color: #64748b;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }

        .results-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .results-table tr:last-child td {
            border-bottom: none;
        }

        .participant-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .participant-photo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .participant-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 1rem;
        }

        .vote-count-cell {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .vote-percent {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .winner-card {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 2px solid #fcd34d;
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(251, 191, 36, 0.1);
            position: relative;
            overflow: hidden;
        }

        .winner-card::before {
            content: '🏆';
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 5rem;
            opacity: 0.1;
            transform: rotate(15deg);
        }

        .winner-photo-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .winner-label {
            display: inline-block;
            background: #f59e0b;
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 1rem;
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
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error" style="background: #FEF2F2; color: #991B1B; border: 1px solid #FEE2E2; padding: 1rem; border-radius: 12px; margin: 2rem 0; text-align: center;">
                <?php if ($_GET['error'] === 'election_not_ended'): ?>
                    ⚠️ Action Blocked: The election must be officially ended before results can be published.
                <?php elseif ($_GET['error'] === 'already_published'): ?>
                    🚫 Action Blocked: The election result has already been published and cannot be unpublished.
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
                <?php if ($election['is_published']): ?>
                    <span class="status-badge"
                        style="background: #dcfce7; padding: 0.5rem 1.25rem; border-radius: 30px; font-size: 0.85rem; font-weight: 700; color: #166534; margin-left: 0.5rem;">
                        📢 RESULTS PUBLISHED
                    </span>
                <?php else: ?>
                    <span class="status-badge live-pulse-indicator"
                        style="background: #fef2f2; padding: 0.5rem 1.25rem; border-radius: 30px; font-size: 0.85rem; font-weight: 700; color: #dc2626; margin-left: 0.5rem; display: <?php echo ($election['voting_status'] === 'active' ? 'inline-flex' : 'none'); ?>; align-items: center; gap: 0.5rem;">
                        <span style="width: 8px; height: 8px; background: #dc2626; border-radius: 50%; display: inline-block; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></span>
                        LIVE UDPATES
                    </span>
                <?php endif; ?>
            </div>
            <style>
                @keyframes ping {
                    75%, 100% { transform: scale(2); opacity: 0; }
                }
            </style>

            <?php if (!$is_published && !$is_admin && !$is_faculty): ?>
                <div style="text-align: center; padding: 5rem 2rem; background: white; border-radius: 2rem; border: 1px solid #e2e8f0; margin-top: 2rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
                    <div style="font-size: 4rem; margin-bottom: 1.5rem;">📣</div>
                    <h2 style="color: #1e293b; font-weight: 800; margin-bottom: 1rem;">Coming Soon</h2>
                    <p style="color: #64748b; font-size: 1.1rem; max-width: 500px; margin: 0 auto 2rem;">Election result has not been published yet. Please check back later once the administrator finalizes the counts.</p>
                    <a href="student_dashboard.php" class="btn btn-primary" style="padding: 1rem 2rem; border-radius: 1rem;">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="result-card">
                    <h3 style="margin-bottom: 2rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; color: #1e293b; font-weight: 800;">
                        Official Standings
                    </h3>

                    <?php
                    $results_array = [];
                    $winner = null;
                    $max_votes = -1;

                    while ($row = $results->fetch_assoc()) {
                        $results_array[] = $row;
                        if ($row['vote_count'] > $max_votes) {
                            $max_votes = $row['vote_count'];
                            $winner = $row;
                        }
                    }

                    // Display only for Students (Winning Candidate Only)
                    if ($is_student):
                        if ($winner && $winner['vote_count'] > 0): 
                    ?>
                            <div class="winner-card" style="margin: 0 auto; background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%); border: 2px solid #fde047; padding: 2rem; border-radius: 1.5rem; text-align: center; max-width: 450px;">
                                <div class="winner-label" style="color: #854d0e; font-size: 0.8rem; letter-spacing: 0.15em; font-weight: 800; text-transform: uppercase;">🏆 Election Winner</div>
                                <div style="margin: 1.25rem 0;">
                                    <img src="<?php echo htmlspecialchars($winner['photo_path']); ?>" alt="Winner" style="width: 120px; height: 120px; border-radius: 50%; border: 5px solid white; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); object-fit: cover;">
                                </div>
                                <h1 style="margin: 0.5rem 0; color: #1e293b; font-size: 1.8rem; font-weight: 800;"><?php echo htmlspecialchars($winner['name']); ?></h1>
                                <p style="font-size: 1.1rem; color: #713f12; font-weight: 600; margin-bottom: 0.75rem;"><?php echo htmlspecialchars($winner['department']); ?></p>
                                
                                <div style="background: white; display: inline-block; padding: 0.6rem 1.5rem; border-radius: 0.85rem; border: 1px solid #fde047; margin-bottom: 1.25rem;">
                                    <span style="font-size: 1rem; color: #713f12; font-weight: 700;">Total Votes: </span>
                                    <span style="font-size: 1.35rem; font-weight: 900; color: #b45309;"><?php echo $winner['vote_count']; ?></span>
                                </div>

                                <div style="margin-top: 0.5rem;">
                                    <h2 style="color: #166534; font-weight: 800; font-size: 1.2rem;">✨ Congratulations! ✨</h2>
                                </div>
                            </div>
                    <?php else: ?>
                            <div style="text-align: center; padding: 4rem; background: #f8fafc; border-radius: 1.5rem; color: #64748b;">
                                <p style="font-size: 1.2rem;">No votes were cast in this election.</p>
                            </div>
                    <?php 
                        endif;
                    else: 
                        // Full view for Admins and Faculty
                        if ($winner && $winner['vote_count'] > 0): 
                    ?>
                        <div class="winner-card" style="margin-bottom: 3rem; background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%); border: 2px solid #fde047; padding: 2.5rem; border-radius: 1.5rem; text-align: center;">
                            <div class="winner-label" style="color: #854d0e; font-size: 0.85rem; letter-spacing: 0.15em; font-weight: 800; text-transform: uppercase;">🏆 Election Winner</div>
                            <div style="margin: 1.5rem 0;">
                                <img src="<?php echo htmlspecialchars($winner['photo_path']); ?>" alt="Winner" style="width: 140px; height: 140px; border-radius: 50%; border: 6px solid white; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); object-fit: cover;">
                            </div>
                            <h2 style="margin: 0.5rem 0; color: #1e293b; font-size: 2.25rem; font-weight: 800;"><?php echo htmlspecialchars($winner['name']); ?></h2>
                            <div style="font-size: 1.1rem; color: #713f12; font-weight: 700; margin-top: 1rem;">
                                Received Total Votes: <span style="font-size: 2rem; font-weight: 900; color: #b45309;"><?php echo $winner['vote_count']; ?></span>
                            </div>
                        </div>
                    <?php elseif ($is_published): ?>
                        <div style="text-align: center; padding: 3rem; background: #f8fafc; border-radius: 1rem; color: #64748b; margin-bottom: 2rem;">
                            No votes have been cast in this election.
                        </div>
                    <?php endif; ?>

                        <table class="results-table">
                            <thead>
                                <tr style="text-align: left; border-bottom: 2px solid #f1f5f9;">
                                    <th style="padding: 1rem; color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Participant</th>
                                    <th style="padding: 1rem; text-align: right; color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Votes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results_array as $cand): ?>
                                    <tr style="border-bottom: 1px solid #f8fafc;">
                                        <td style="padding: 1.25rem 1rem;">
                                            <div class="participant-info" style="display: flex; align-items: center; gap: 1.25rem;">
                                                <img src="<?php echo htmlspecialchars($cand['photo_path']); ?>" alt="Photo" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #eef2ff;">
                                                <div style="font-weight: 700; color: #1e293b; font-size: 1.05rem;">
                                                    <?php echo htmlspecialchars($cand['name']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1.25rem 1rem; text-align: right;">
                                            <span id="vote-count-<?php echo $cand['id']; ?>" style="font-weight: 800; color: #4f46e5; background: #eef2ff; padding: 0.5rem 1rem; border-radius: 0.75rem; font-size: 1rem; border: 1px solid #e0e7ff; transition: all 0.3s;">
                                                <?php echo $cand['vote_count']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php if (empty($results_array)): ?>
                        <div style="text-align: center; padding: 4rem; color: #94a3b8;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                            <p>No candidates were found for this election.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($is_admin || $is_faculty): ?>
                <div style="text-align: center; margin-top: 2.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <?php if (isset($election['is_published']) && $election['is_published']): ?>
                        <div style="background: #ECFDF5; border: 1px solid #10B981; color: #065F46; padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                            ✅ Results Finalized & Published
                        </div>
                    <?php else: ?>
                        <?php if ($election['voting_status'] === 'ended' && $is_admin): ?>
                            <a href="update_status.php?id=<?php echo $election['id']; ?>&type=publish&value=1&redirect=view_results"
                                class="btn btn-primary" style="background: #10b981; border-color: #059669;"
                                onclick="return confirm('Note: Once published, results are final and cannot be unpublished. Continue?')">
                                📢 Publish Final Results
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <button onclick="window.print()" class="btn btn-secondary"
                        style="border: 1px solid #e2e8f0; background: white; color: #475569;">🖨️ Print Official Report</button>
                    <a href="admin_dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Live Real-Time Polling Script -->
    <?php if ($election_id && !$is_published && (!isset($election['voting_status']) || $election['voting_status'] === 'active')): ?>
    <script>
        function fetchLiveResults() {
            fetch('view_results.php?ajax_live=1&id=<?php echo $election_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.counts) {
                        for (const [candidateId, count] of Object.entries(data.counts)) {
                            const countEl = document.getElementById('vote-count-' + candidateId);
                            if (countEl) {
                                // If count changed, animate it to highlight the real-time update
                                if (countEl.innerText != count) {
                                    countEl.innerText = count;
                                    countEl.style.backgroundColor = '#dcfce7'; // green flash
                                    countEl.style.color = '#166534';
                                    countEl.style.borderColor = '#bbf7d0';
                                    countEl.style.transform = 'scale(1.1)';
                                    
                                    setTimeout(() => {
                                        countEl.style.backgroundColor = '#eef2ff';
                                        countEl.style.color = '#4f46e5';
                                        countEl.style.borderColor = '#e0e7ff';
                                        countEl.style.transform = 'scale(1)';
                                    }, 800);
                                }
                            }
                        }
                    }
                })
                .catch(error => console.error('Error fetching live results:', error));
        }

        // Poll every 3 seconds for fast, optimized real-time updates without crashing the server
        setInterval(fetchLiveResults, 3000);
    </script>
    <?php endif; ?>

</body>

</html>