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

if (!$election)
    redirect('dashboard.php', 'Election not found.', 'error');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register as Candidate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header text-primary font-weight-bold">Candidate Registration for
                <?php echo $election['name']; ?></div>
            <div class="card-body">
                <form action="save_candidate.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">

                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo $_SESSION['name']; ?>"
                            readonly>
                    </div>
                    <div class="mb-3">
                        <label>College ID</label>
                        <input type="text" name="college_id" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Department</label>
                            <input type="text" name="department" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Year</label>
                            <input type="text" name="year" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Gender</label>
                            <select name="gender" class="form-control" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Profile Photo</label>
                        <input type="file" name="photo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>ID Proof (College ID / Aadhaar / PAN / License)</label>
                        <input type="file" name="proof" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Registration</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</body>

</html>