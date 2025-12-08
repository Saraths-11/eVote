<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();

$electionId = $_GET['id'] ?? null;
if (!$electionId)
    redirect('dashboard.php');

$elections = getJSONData('elections.json');
$election = null;
foreach ($elections as $e) {
    if ($e['id'] === $electionId) {
        $election = $e;
        break;
    }
}

if (!$election)
    redirect('dashboard.php', 'Election not found.', 'error');

$candidates = getJSONData('candidates.json');
$electionCandidates = array_filter($candidates, function ($c) use ($electionId) {
    return $c['election_id'] === $electionId;
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Election</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo $election['name']; ?> (<?php echo $election['year']; ?>)</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <?php displayFlashMessage(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Election Status: <span
                        class="badge bg-info"><?php echo $election['status']; ?></span></h6>
            </div>
            <div class="card-body">
                <form action="election_status.php" method="POST" class="d-inline">
                    <input type="hidden" name="id" value="<?php echo $election['id']; ?>">
                    <?php if ($election['status'] == 'Upcoming'): ?>
                        <button type="submit" name="status" value="Open" class="btn btn-success">Open Voting</button>
                    <?php elseif ($election['status'] == 'Open'): ?>
                        <button type="submit" name="status" value="Closed" class="btn btn-danger">Close Voting</button>
                    <?php endif; ?>
                </form>
                <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-primary">View Results</a>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Candidates</h6>
                <a href="manage_requests.php?id=<?php echo $election['id']; ?>" class="btn btn-warning btn-sm">Manage
                    Requests</a>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($electionCandidates as $candidate): ?>
                            <tr>
                                <td><?php echo $candidate['name']; ?></td>
                                <td><?php echo $candidate['department']; ?></td>
                                <td>
                                    <span
                                        class="badge bg-<?php echo $candidate['status'] == 'Approved' ? 'success' : ($candidate['status'] == 'Rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo $candidate['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>