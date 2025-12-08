<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();

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

if (!$election || $election['status'] !== 'Open') {
    redirect('dashboard.php', 'Voting is not open for this election.', 'error');
}

// Check if already voted
$voters = getJSONData('voters.json');
foreach ($voters as $v) {
    if ($v['election_id'] === $electionId && $v['student_id'] === $_SESSION['user_id']) {
        redirect('dashboard.php', 'You have already voted in this election.', 'warning');
    }
}

$candidates = getJSONData('candidates.json');
$approvedCandidates = array_filter($candidates, function ($c) use ($electionId) {
    return $c['election_id'] === $electionId && $c['status'] === 'Approved';
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Vote Now</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <h1 class="text-center mb-4">Vote for <?php echo $election['name']; ?></h1>

        <div class="alert alert-info">
            <strong>Verify Identity:</strong> You are voting as <strong><?php echo $_SESSION['name']; ?></strong>
            (<?php echo $_SESSION['email']; ?>).
        </div>

        <form action="submit_vote.php" method="POST">
            <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">

            <div class="row">
                <?php foreach ($approvedCandidates as $candidate): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 shadow-sm candidate-card">
                            <div class="card-body text-center">
                                <?php if ($candidate['photo']): ?>
                                    <img src="../uploads/<?php echo $candidate['photo']; ?>" class="rounded-circle mb-3"
                                        style="width: 150px; height: 150px; object-fit: cover;">
                                <?php endif; ?>
                                <h5><?php echo $candidate['name']; ?></h5>
                                <p class="text-muted"><?php echo $candidate['department']; ?></p>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="radio" name="candidate_id"
                                        value="<?php echo $candidate['id']; ?>" id="cand_<?php echo $candidate['id']; ?>"
                                        required>
                                    <label class="form-check-label ms-2" for="cand_<?php echo $candidate['id']; ?>">
                                        Vote
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn btn-success btn-lg px-5">Submit Vote</button>
                <a href="dashboard.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
            </div>
        </form>
    </div>
</body>

</html>