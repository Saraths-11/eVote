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

$candidates = getJSONData('candidates.json');
$electionCandidates = array_filter($candidates, function ($c) use ($electionId) {
    return $c['election_id'] === $electionId && $c['status'] === 'Approved';
});

$votes = getJSONData('votes.json');
$voteCounts = [];
$totalVotes = 0;

foreach ($electionCandidates as $c) {
    $voteCounts[$c['id']] = 0;
}

foreach ($votes as $v) {
    if ($v['election_id'] === $electionId) {
        if (isset($voteCounts[$v['candidate_id']])) {
            $voteCounts[$v['candidate_id']]++;
            $totalVotes++;
        }
    }
}

// Sort by votes descending
uasort($electionCandidates, function ($a, $b) use ($voteCounts) {
    return $voteCounts[$b['id']] - $voteCounts[$a['id']];
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Election Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Results: <?php echo $election['name']; ?></h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Total Votes Cast: <?php echo $totalVotes; ?></h6>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Candidate Name</th>
                            <th>Department</th>
                            <th>Votes</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rank = 1;
                        foreach ($electionCandidates as $candidate):
                            $count = $voteCounts[$candidate['id']];
                            $percentage = $totalVotes > 0 ? round(($count / $totalVotes) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td>
                                    <?php if ($candidate['photo']): ?>
                                        <img src="../uploads/<?php echo $candidate['photo']; ?>" class="rounded-circle me-2"
                                            style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php endif; ?>
                                    <?php echo $candidate['name']; ?>
                                </td>
                                <td><?php echo $candidate['department']; ?></td>
                                <td><strong><?php echo $count; ?></strong></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-success" role="progressbar"
                                            style="width: <?php echo $percentage; ?>%;"
                                            aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0"
                                            aria-valuemax="100"><?php echo $percentage; ?>%</div>
                                    </div>
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