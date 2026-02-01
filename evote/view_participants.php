<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $participant_id = $_POST['participant_id'] ?? 0;
    $reason = $_POST['reason'] ?? null;

    if ($participant_id) {
        $stmt = null;
        if ($action === 'Cancelled') {
            $stmt = $conn->prepare("UPDATE participants SET status = ?, cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $action, $reason, $participant_id);
        } elseif ($action === 'Rejected') {
            $stmt = $conn->prepare("UPDATE participants SET status = ?, rejection_reason = ? WHERE id = ?");
            $stmt->bind_param("ssi", $action, $reason, $participant_id);
        } elseif ($action === 'Approved') {
            $stmt = $conn->prepare("UPDATE participants SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $action, $participant_id);
        }

        if ($stmt && $stmt->execute()) {
            header("Location: view_participants.php?msg=" . strtolower($action) . "&view=" . ($_GET['view'] ?? 'Pending'));
            exit();
        }
    }
}

// Handle View Filter
$current_view = $_GET['view'] ?? 'Pending';
$allowed_views = ['Pending', 'Approved', 'Rejected', 'All'];
if (!in_array($current_view, $allowed_views)) {
    $current_view = 'Pending';
}

// Fetch participants
$sql = "SELECT p.*, e.title as election_title FROM participants p 
        JOIN elections e ON p.election_id = e.id ";

if ($current_view !== 'All') {
    $sql .= "WHERE p.status = '" . $conn->real_escape_string($current_view) . "' ";
}

$sql .= "ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Verify Participants - eVote Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #4F46E5;
            --pending: #F59E0B;
            --approved: #10B981;
            --rejected: #EF4444;
        }

        .tab-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #6b7280;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .tab-btn:hover {
            color: var(--primary);
            background: #f3f4f6;
        }

        .tab-btn.active {
            color: var(--primary);
            background: #eff6ff;
            border-color: #dbeafe;
        }

        .p-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .p-table th,
        .p-table td {
            padding: 1.25rem;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }

        .p-table th {
            background: #f9fafb;
            color: #4b5563;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-Pending {
            background: #FFF7ED;
            color: #9A3412;
        }

        .status-Approved {
            background: #ECFDF5;
            color: #065F46;
        }

        .status-Rejected {
            background: #FEF2F2;
            color: #991B1B;
        }

        .status-Cancelled {
            background: #F9FAFB;
            color: #4B5563;
        }

        .thumb-box {
            width: 56px;
            height: 56px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .thumb-box:hover {
            transform: scale(1.1);
        }

        .thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-top: 1rem;
        }

        .info-group {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }

        .info-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 0.9rem;
            color: #1e293b;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <nav class="dashboard-nav">
        <div class="nav-brand">eVote Admin</div>
        <div class="nav-menu">
            <span>Welcome, <strong>
                    <?php echo htmlspecialchars($_SESSION['name']); ?>
                </strong></span>
            <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Logout</a>
            <a href="admin_dashboard.php" class="btn btn-secondary"
                style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-left: 0.5rem;">Back to Dashboard</a>
        </div>
    </nav>

    <div class="dashboard-content">
        <div class="welcome-banner" style="margin-bottom: 2rem;">
            <h2>Manage Participants</h2>
            <p>Verify identity proofs and approve student candidates.</p>
        </div>

        <div class="tab-nav">
            <?php foreach (['Pending', 'Approved', 'Rejected', 'All'] as $view): ?>
                <a href="?view=<?php echo $view; ?>" class="tab-btn <?php echo $current_view === $view ? 'active' : ''; ?>">
                    <?php echo $view; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <table class="p-table">
                <thead>
                    <tr>
                        <th>Student Candidate</th>
                        <th>Election Assignment</th>
                        <th>Profile Details</th>
                        <th>Verification Docs</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr style="background: white;">
                            <td style="width: 280px;">
                                <div style="display: flex; gap: 1rem; align-items: center;">
                                    <div class="thumb-box" onclick="viewImg('<?php echo $row['photo_path']; ?>')">
                                        <img src="<?php echo $row['photo_path']; ?>">
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: #111827; font-size: 1rem;">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </div>
                                        <div style="color: #6b7280; font-size: 0.8rem; font-family: monospace;">
                                            <?php echo $row['college_id']; ?>
                                        </div>
                                        <span class="status-badge status-<?php echo $row['status']; ?>"
                                            style="margin-top: 0.5rem; display: inline-block;">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #4b5563; font-size: 0.9rem; max-width: 200px;">
                                    <?php echo htmlspecialchars($row['election_title']); ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; line-height: 1.6;">
                                    <span style="color: #94a3b8;">DEPT:</span> <span style="font-weight: 600; color: #334155;">
                                        <?php echo $row['department']; ?>
                                    </span><br>
                                    <span style="color: #94a3b8;">YEAR:</span> <span style="font-weight: 600; color: #334155;">
                                        <?php echo $row['year']; ?>
                                    </span><br>
                                    <span style="color: #94a3b8;">DOB:</span> <span style="font-weight: 600; color: #334155;">
                                        <?php echo date('M d, Y', strtotime($row['dob'])); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <a href="#" onclick="viewImg('<?php echo $row['proof_path']; ?>'); return false;"
                                        style="font-size: 0.8rem; font-weight: 700; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 0.4rem;">
                                        📎 View ID Proof
                                    </a>
                                    <a href="#" onclick="viewImg('<?php echo $row['signature_path']; ?>'); return false;"
                                        style="font-size: 0.8rem; font-weight: 700; color: #10b981; text-decoration: none; display: flex; align-items: center; gap: 0.4rem;">
                                        ✍️ View Signature
                                    </a>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'Pending'): ?>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <form method="POST" onsubmit="return confirm('Approve this participant?');">
                                            <input type="hidden" name="participant_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="Approved">
                                            <button type="submit" class="btn btn-sm"
                                                style="background: #10B981; color: white; border: none; padding: 0.5rem 0.8rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST"
                                            onsubmit="return handleAction(this, 'Rejected', 'Reason for rejection:');">
                                            <input type="hidden" name="participant_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="Rejected">
                                            <button type="submit" class="btn btn-sm"
                                                style="background: #EF4444; color: white; border: none; padding: 0.5rem 0.8rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($row['status'] == 'Rejected'): ?>
                                    <div
                                        style="font-size: 0.8rem; color: #64748b; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 8px; max-width: 200px;">
                                        <strong>Reason:</strong><br>
                                        <?php echo htmlspecialchars($row['rejection_reason'] ?? 'No reason provided'); ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">Status Locked</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div
                style="text-align: center; padding: 5rem; background: white; border-radius: 20px; border: 2px dashed #e5e7eb;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📂</div>
                <h3 style="color: #4b5563;">No
                    <?php echo $current_view; ?> Participants Found
                </h3>
                <p style="color: #94a3b8;">There are currently no registration requests in this category.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Simple Image Preview Modal -->
    <div id="imgModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
        <div style="position: absolute; top: 20px; right: 20px; z-index: 1001;">
            <a id="downloadLink" href="#" download class="btn btn-primary"
                style="background: white; color: var(--primary); border: none; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600;">Download
                Image</a>
            <button onclick="document.getElementById('imgModal').style.display='none'" class="btn btn-secondary"
                style="margin-left: 10px; background: rgba(255,255,255,0.2); border: 1px solid white; color: white; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer;">Close</button>
        </div>
        <img id="modalImg" style="max-width: 90%; max-height: 90%; border-radius: 8px; cursor: zoom-in;"
            onclick="window.open(this.src, '_blank')">
    </div>

    <script>
        function handleAction(form, action, message) {
            let reason = prompt(message);
            if (reason === null) return false; // Canceled by admin

            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reason';
            input.value = reason;
            form.appendChild(input);
            return true;
        }

        function viewImg(src) {
            const modal = document.getElementById('imgModal');
            document.getElementById('modalImg').src = src;
            document.getElementById('downloadLink').href = src;
            modal.style.display = "flex";
        }

        // Close modal if clicked outside
        window.onclick = function (event) {
            const modal = document.getElementById('imgModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>

</html>