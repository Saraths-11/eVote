<?php
session_start();
require_once 'api/db_connect.php';

$error = "";

// Hardcoded Admin Credentials
$ADMIN_EMAIL = "sarathsakumar2028@mca.ajce.in";
$ADMIN_PASSWORD = "2004";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Domain Validation
    if (!str_ends_with($email, '@mca.ajce.in')) {
        $error = "Access Denied: Only @mca.ajce.in emails are allowed.";
    } else {
        // 2. Check if Admin
        if ($email === $ADMIN_EMAIL) {
            if ($password === $ADMIN_PASSWORD) {
                // Admin Login Success
                $_SESSION['user_id'] = 0; // Special ID for admin
                $_SESSION['name'] = "Administrator";
                $_SESSION['email'] = $email;
                $_SESSION['role'] = "admin";

                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Invalid admin credentials.";
            }
        } else {
            // 3. Student Login (Check Database)
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Student Login Success
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = "student";

                    header("Location: student_dashboard.php");
                    exit();
                } else {
                    $error = "Invalid email or password.";
                }
            } catch (Exception $e) {
                $error = "System Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Error</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #f4f6f9;
            margin: 0;
        }

        .card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .error {
            color: #e74c3c;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 18px;
        }

        a {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            display: inline-block;
            transition: background 0.3s;
        }

        a:hover {
            background: #2980b9;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <a href="login.html">Try Again</a>
    </div>
</body>

</html>