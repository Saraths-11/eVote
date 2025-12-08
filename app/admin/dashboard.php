<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();

$elections = getJSONData('elections.json');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body id="page-top">
    <nav class="navbar navbar-expand navbar-dark bg-primary topbar mb-4 static-top shadow">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Dashboard</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
            <a href="create_election.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                Create New Election
            </a>
        </div>

        <?php displayFlashMessage(); ?>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Election List</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($elections)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No elections found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($elections as $election): ?>
                                            <tr>
                                                <td><?php echo $election['id']; ?></td>
                                                <td><?php echo $election['name']; ?></td>
                                                <td><?php echo $election['year']; ?></td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?php echo $election['status'] == 'Open' ? 'success' : ($election['status'] == 'Closed' ? 'danger' : 'warning'); ?>">
                                                        <?php echo $election['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="manage_election.php?id=<?php echo $election['id']; ?>"
                                                        class="btn btn-info btn-sm">Manage</a>
                                                    <a href="results.php?id=<?php echo $election['id']; ?>"
                                                        class="btn btn-secondary btn-sm">Results</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>