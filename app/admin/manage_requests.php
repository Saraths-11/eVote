<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();

$electionId = $_GET['id'] ?? null;
if (!$electionId)
    redirect('dashboard.php');

$candidates = getJSONData('candidates.json');
$pendingCandidates = array_filter($candidates, function ($c) use ($electionId) {
    return $c['election_id'] === $electionId && $c['status'] === 'Pending';
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Candidate Requests</h1>
            <a href="manage_election.php?id=<?php echo $electionId; ?>" class="btn btn-secondary">Back</a>
        </div>

        <?php displayFlashMessage(); ?>

        <div class="card shadow">
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>College ID</th>
                            <th>Dept</th>
                            <th>Photo</th>
                            <th>ID Proof</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingCandidates)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No pending requests.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingCandidates as $candidate): ?>
                                <tr>
                                    <td><?php echo $candidate['name']; ?></td>
                                    <td><?php echo $candidate['college_id']; ?></td>
                                    <td><?php echo $candidate['department']; ?></td>
                                    <td>
                                        <a href="../uploads/<?php echo $candidate['photo']; ?>" target="_blank">View Photo</a>
                                    </td>
                                    <td>
                                        <a href="../uploads/<?php echo $candidate['proof']; ?>" target="_blank">View Proof</a>
                                    </td>
                                    <td>
                                        <form action="process_request.php" method="POST" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $candidate['id']; ?>">
                                            <input type="hidden" name="election_id" value="<?php echo $electionId; ?>">
                                            <button type="submit" name="action" value="Approve"
                                                class="btn btn-success btn-sm">Approve</button>
                                            <button type="submit" name="action" value="Reject"
                                                class="btn btn-danger btn-sm">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>