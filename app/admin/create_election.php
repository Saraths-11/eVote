<?php
require_once '../includes/auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Election</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header text-primary font-weight-bold">Create New Election</div>
            <div class="card-body">
                <form action="save_election.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label>Election Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Year</label>
                        <input type="number" name="year" class="form-control" value="<?php echo date('Y'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Poster</label>
                        <input type="file" name="poster" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Registration Start</label>
                            <input type="date" name="reg_start" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Registration End</label>
                            <input type="date" name="reg_end" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Election</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</body>

</html>