<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireStudent();

$elections = getJSONData('elections.json');
$candidates = getJSONData('candidates.json');
$myCandidacies = array_filter($candidates, function ($c) {
    return $c['student_id'] === $_SESSION['user_id'];
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body id="page-top">
    <nav class="navbar navbar-expand navbar-dark bg-primary topbar mb-4 static-top shadow">
        <div class="container">
            <a class="navbar-brand" href="#">Student Dashboard</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-white">Welcome, <?php echo $_SESSION['name']; ?></span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php displayFlashMessage(); ?>

        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">Available Elections</h2>
                <div class="row">
                    <?php if (empty($elections)): ?>
                        <div class="col-12">
                            <p>No elections available.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($elections as $election): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card shadow h-100">
                                    <?php if ($election['poster']): ?>
                                        <img src="../uploads/<?php echo $election['poster']; ?>" class="card-img-top" alt="Poster"
                                            style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center"
                                            style="height: 200px;">No Poster</div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo $election['name']; ?></h5>
                                        <p class="card-text">Status: <span
                                                class="badge bg-<?php echo $election['status'] == 'Open' ? 'success' : 'secondary'; ?>"><?php echo $election['status']; ?></span>
                                        </p>

                                        <?php
                                        $isCandidate = false;
                                        $status = '';
                                        foreach ($myCandidacies as $c) {
                                            if ($c['election_id'] === $election['id']) {
                                                $isCandidate = true;
                                                $status = $c['status'];
                                                break;
                                            }
                                        }
                                        ?>

                                        <?php if ($election['status'] == 'Open'): ?>
                                            <a href="vote.php?id=<?php echo $election['id']; ?>" class="btn btn-success w-100">Vote
                                                Now</a>
                                        <?php elseif ($election['status'] == 'Upcoming'): ?>
                                            <?php if ($isCandidate): ?>
                                                <button class="btn btn-info w-100" disabled>Candidacy: <?php echo $status; ?></button>
                                            <?php else: ?>
                                                <a href="register.php?id=<?php echo $election['id']; ?>"
                                                    class="btn btn-primary w-100">Register as Candidate</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="results.php?id=<?php echo $election['id']; ?>"
                                                class="btn btn-secondary w-100">View Results</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>