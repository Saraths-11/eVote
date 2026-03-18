<?php
// forgot_password.php
session_start();
include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - eVote</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Forgot Password?</h2>
                <p>Enter your email to reset your password</p>
            </div>

            <div id="status-message" style="display: none;" class="alert"></div>

            <form id="forgot-form">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="email" class="form-control" placeholder="Enter your email" required>
                </div>

                <button type="submit" id="submit-btn" class="btn btn-primary" style="width: 100%;">Send Reset
                    Link</button>
            </form>

            <p style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem;">
                Remember your password? <a href="login.php" style="color: var(--primary); font-weight: 600;">Login</a>
            </p>
        </div>
    </div>

    <!-- Firebase SDKs -->
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getAuth, sendPasswordResetEmail } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

        // Firebase Configuration (Same as in login/signup)
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
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);

        const form = document.getElementById('forgot-form');
        const statusDiv = document.getElementById('status-message');
        const submitBtn = document.getElementById('submit-btn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();

            // Clear previous messages
            statusDiv.style.display = 'none';

            // 1. Validation
            if (!email) {
                showStatus("Please enter an email address.", "error");
                return;
            }

            // Basic Email Regex
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showStatus("Please enter a valid email address.", "error");
                return;
            }

            // Disable button
            submitBtn.innerText = "Checking...";
            submitBtn.disabled = true;

            try {
                // 1. Check if email exists in our records first
                const checkResponse = await fetch('check_email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });

                const checkData = await checkResponse.json();

                if (!checkData.success) {
                    throw new Error(checkData.message || "Failed to verify account.");
                }

                if (!checkData.exists) {
                    showStatus("Error: No account found with this email address. Please sign up first.", "error");
                    submitBtn.innerText = "Send Reset Link";
                    submitBtn.disabled = false;
                    return;
                }

                // 2. Email exists -> Send Firebase Reset Email
                submitBtn.innerText = "Sending...";
                await sendPasswordResetEmail(auth, email);

                showStatus("Success! A reset link has been sent to your email.", "success");
                submitBtn.innerText = "Sent";
                form.reset();

                // Re-enable after a few seconds
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerText = "Send Reset Link";
                }, 5000);

            } catch (error) {
                console.error("Reset Error:", error);

                let msg = "Failed to send reset email.";
                if (error.code === 'auth/user-not-found') {
                    msg = "No account found in the authentication system.";
                } else if (error.code === 'auth/invalid-email') {
                    msg = "Invalid email format.";
                } else if (error.code === 'auth/too-many-requests') {
                    msg = "Too many requests. Please try again later.";
                } else {
                    msg = error.message || "An unexpected error occurred.";
                }

                showStatus(msg, "error");
                submitBtn.innerText = "Send Reset Link";
                submitBtn.disabled = false;
            }
        });

        function showStatus(message, type) {
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = message;
            if (type === 'error') {
                statusDiv.className = 'alert alert-error';
            } else {
                statusDiv.className = 'alert alert-success';
            }
        }
    </script>
</body>

</html>