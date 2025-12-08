<?php
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $users = getJSONData('users.json');

    // Check if email exists
    foreach ($users as $u) {
        if ($u['email'] === $email) {
            redirect('register_user.php', 'Email already exists.', 'error');
        }
    }

    $newUser = [
        'id' => uniqid('stu_'),
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => 'student',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $users[] = $newUser;
    saveJSONData('users.json', $users);
    redirect('../index.php', 'Account created! Please login.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-gradient-primary">
    <div class="container">
        <div class="card o-hidden border-0 shadow-lg my-5 card-login">
            <div class="card-body p-0">
                <div class="row">
                    <div class="col-lg-5 d-none d-lg-block bg-register-image"
                        style="background: url('https://source.unsplash.com/random/600x800?student') center; background-size: cover;">
                    </div>
                    <div class="col-lg-7">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">Create an Account!</h1>
                                <?php displayFlashMessage(); ?>
                            </div>
                            <form class="user" action="register_user.php" method="POST">
                                <div class="form-group mb-3">
                                    <input type="text" class="form-control form-control-user" name="name"
                                        placeholder="Full Name" required>
                                </div>
                                <div class="form-group mb-3">
                                    <input type="email" class="form-control form-control-user" name="email"
                                        placeholder="Email Address" required>
                                </div>
                                <div class="form-group mb-3">
                                    <input type="password" class="form-control form-control-user" name="password"
                                        placeholder="Password" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-user btn-block w-100">
                                    Register Account
                                </button>
                            </form>
                            <hr>
                            <div class="text-center">
                                <a class="small" href="../index.php">Already have an account? Login!</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>