<?php
session_start();
include 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    } else {
        // Hardcoded Admin Check
        if ($email === 'sarath123@gmail.com' && $password === '2004') {
            $_SESSION['user_id'] = 0;
            $_SESSION['name'] = 'Administrator';
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'admin';
            header("Location: admin_dashboard.php");
            exit();
        } else {
            // SQL Fallback for Students/Faculty (needed for Selenium tests & non-Firebase users)
            $stmt = $conn->prepare("SELECT id, accountFullName, password, role FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['name'] = $row['accountFullName'];
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = $row['role'];

                    if ($row['role'] === 'admin') {
                        header("Location: admin_dashboard.php");
                    } elseif ($row['role'] === 'faculty') {
                        header("Location: faculty_dashboard.php");
                    } else {
                        header("Location: student_dashboard.php");
                    }
                    exit();
                }
            }
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - eVote</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Welcome Back</h2>
                <p>Login to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email address"
                        required autocomplete="off">
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label class="form-label" style="margin-bottom:0;">Password</label>
                        <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password"
                        required style="margin-top: 0.5rem;" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>

                <div class="auth-separator">OR</div>

                <a href="#" class="btn btn-google">
                    <svg width="20" height="20" viewBox="0 0 48 48">
                        <path fill="#FFC107"
                            d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z" />
                        <path fill="#FF3D00"
                            d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z" />
                        <path fill="#4CAF50"
                            d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z" />
                        <path fill="#1976D2"
                            d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z" />
                    </svg>
                    Continue with Google
                </a>
            </form>

            <p style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem;">
                Don't have an account? <a href="signup.php" style="color: var(--primary); font-weight: 600;">Sign Up</a>
            </p>
        </div>
    </div>

    <!-- Firebase SDKs -->
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getAuth, signInWithPopup, GoogleAuthProvider, signInWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

        const firebaseConfig = {
            apiKey: "AIzaSyDYSy9FP6v6r2hiU8nZEpEbUs5AaEL-ddw",
            authDomain: "evote-47826.firebaseapp.com",
            projectId: "evote-47826",
            storageBucket: "evote-47826.firebasestorage.app",
            messagingSenderId: "184727402038",
            appId: "1:184727402038:web:8a592fe47b872ccf290a97",
            measurementId: "G-LNW7ZRN2VZ"
        };

        // Initialize Firebase
        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const provider = new GoogleAuthProvider();

        const loginForm = document.querySelector('form');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = loginForm.querySelector('button');
            const originalText = btn.innerText;
            btn.innerText = "Verifying...";

            const email = loginForm.email.value;
            const password = loginForm.password.value;

            try {
                // Try Firebase Login First
                const userCredential = await signInWithEmailAndPassword(auth, email, password);
                const user = userCredential.user;
                console.log("Firebase Login Success", user);

                // Sync with backend
                const response = await fetch('google_auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: user.displayName, // Function google_auth handles empty name if exists in DB
                        email: user.email,
                        uid: user.uid
                    })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    // Backend rejected it? Fallback to PHP submit just in case or show error
                    alert(data.message);
                    btn.innerText = originalText;
                }

            } catch (error) {
                console.log("Firebase Login Failed:", error.code);

                // Fallback to PHP for SQL-based users (like in Selenium tests)
                console.log("Firebase Login Failed, trying SQL fallback...");
                loginForm.submit();
                return;

                // For everyone else (Students/Faculty), show the specific error from Firebase
                // instead of submitting to PHP (which would just say "Invalid Credentials" because it doesn't check DB anymore).
                btn.innerText = originalText;

                let msg = "Login failed: " + error.message;
                if (error.code === 'auth/wrong-password' || error.code === 'auth/invalid-credential') {
                    msg = "Incorrect email or password.";
                } else if (error.code === 'auth/user-not-found') {
                    msg = "No account found with this email.";
                } else if (error.code === 'auth/too-many-requests') {
                    msg = "Access temporarily disabled due to many failed login attempts. Reset your password or try again later.";
                }

                alert(msg);
            }
        });

        const googleBtn = document.querySelector('.btn-google');

        googleBtn.addEventListener('click', (e) => {
            e.preventDefault();

            if (googleBtn.disabled) return;
            googleBtn.disabled = true;
            googleBtn.style.opacity = "0.6";

            signInWithPopup(auth, provider)
                .then((result) => {
                    const user = result.user;
                    console.log("Google Sign-In Success", user);

                    // Send to backend
                    fetch('google_auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            name: user.displayName,
                            email: user.email,
                            uid: user.uid
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = data.redirect;
                            } else {
                                alert(data.message);
                                googleBtn.disabled = false;
                                googleBtn.style.opacity = "1";
                            }
                        })
                        .catch(err => {
                            console.error('Backend Error:', err);
                            alert('Authentication failed on server.');
                            googleBtn.disabled = false;
                            googleBtn.style.opacity = "1";
                        });

                }).catch((error) => {
                    console.error("Firebase Error", error);
                    // Ignore closed popup error to avoid spamming user
                    if (error.code !== 'auth/popup-closed-by-user' && error.code !== 'auth/cancelled-popup-request') {
                        alert("Google Sign-In failed: " + error.message);
                    }
                    googleBtn.disabled = false;
                    googleBtn.style.opacity = "1";
                });
        });
    </script>
</body>

</html>