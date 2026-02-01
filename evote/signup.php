<?php
session_start();
include 'config.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    $college_id = trim($_POST['college_id'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $year = trim($_POST['year'] ?? '');

    $role = $_POST['role'] ?? 'student';

    // Validation for Role and Domain
    if ($email === 'sarath123@gmail.com') {
        $role = 'admin'; // Auto-upgrade to admin regardless of selection
    } elseif ($role === 'faculty' && !str_ends_with($email, '@amaljyothi.ac.in')) {
        $error = "Faculty must use an @amaljyothi.ac.in email domain.";
    } elseif ($role === 'student' && !str_ends_with($email, '@mca.ajce.in')) {
        $error = "Students must use an @mca.ajce.in email domain.";
    } elseif ($role !== 'faculty' && $role !== 'student') {
        $role = 'student'; // Default to student
    }

    // Function for strict Name Validation
    function is_valid_human_name($n)
    {
        $n = trim($n);
        if (strlen($n) < 3)
            return false;
        if (!preg_match("/^[a-zA-Z\s]+$/", $n))
            return false;
        // Reject 3+ same characters in a row (e.g., mmmm)
        if (preg_match('/(.)\1{2,}/i', $n))
            return false;
        // Reject names with only 1 unique character (e.g., "aba" is 2 unique, "aaa" is 1)
        if (count(count_chars($n, 1)) < 2)
            return false;
        // Basic meaningless pattern check: Every word should have at least one vowel
        $words = explode(' ', $n);
        foreach ($words as $w) {
            if (strlen($w) >= 2 && !preg_match('/[aeiouAEIOU]/i', $w))
                return false;
        }
        return true;
    }

    // Password Complexity Regex (Min 6 chars, Upper, Lower, Number, Special)
    $pwd_regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{6,}$/";

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || ($role === 'student' && (empty($college_id) || empty($year)))) {
        $error = "All fields are required.";
    } elseif (!is_valid_human_name($name)) {
        $error = "Please enter a valid human name. Avoid random patterns or excessive repetitions.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match($pwd_regex, $password)) {
        $error = "Password must be at least 6 characters and include uppercase, lowercase, number, and special character.";
    } elseif (empty($role)) {
        $error = "Invalid email domain. Students: @mca.ajce.in, Faculty: @amaljyothi.ac.in";
    } elseif ($role === 'student' && !ctype_digit($college_id)) {
        $error = "College ID must contain only numbers.";
    } else {
        // Check if email or college_id already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR (college_id = ? AND college_id != '')");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => "Database Error: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("ss", $email, $college_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email or College ID already registered.";
        } else {
            // Insert User
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (accountFullName, email, password, role, college_id, department, year) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $name, $email, $hashed_password, $role, $college_id, $department, $year);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => "Account created successfully! Redirecting to login..."]);
                exit;
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }

    if ($error) {
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - eVote</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Create Account</h2>
                <p>Join the election system</p>
            </div>

            <div id="js-error" class="alert alert-error" style="display: none;"></div>
            <div id="js-success" class="alert alert-success" style="display: none;"></div>

            <form id="signup-form" method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" id="name-input" class="form-control"
                        placeholder="Enter your full name" required autocomplete="off">
                    <small id="name-feedback"
                        style="color: #dc2626; font-size: 0.85rem; display: none; margin-top: 4px;"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Register As</label>
                    <select name="role" id="role-select" class="form-control" required>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="email-input" class="form-control"
                        placeholder="Enter your email address" required autocomplete="off">
                </div>

                <div id="student-only-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">College ID (Numbers Only)</label>
                        <input type="text" name="college_id" class="form-control" placeholder="Enter College ID"
                            autocomplete="off" pattern="[0-9]*" inputmode="numeric">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-control">
                                <option value="">Select Dept (Optional)</option>
                                <option value="B.Tech">B.Tech</option>
                                <option value="MCA">MCA</option>
                                <option value="INMCA">Integrated MCA (INMCA)</option>
                                <option value="CSE">Computer Science (CSE)</option>
                                <option value="ECE">Electronics (ECE)</option>
                                <option value="EEE">Electrical (EEE)</option>
                                <option value="MECH">Mechanical (MECH)</option>
                                <option value="CIVIL">Civil (CIVIL)</option>
                                <option value="MT">Metallurgical</option>
                                <option value="FT">Food Technology</option>
                                <option value="CH">Chemical Engineering</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-control">
                                <option value="">Select Year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control"
                        placeholder="Create a secure password" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                        placeholder="Confirm your password" required autocomplete="new-password">
                    <small id="password-feedback"
                        style="color: #dc2626; font-size: 0.85rem; display: none; margin-top: 4px;">Passwords do not
                        match.</small>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Sign Up</button>

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
                Already have an account? <a href="login.php" style="color: var(--primary); font-weight: 600;">Login</a>
            </p>
        </div>
    </div>

    <!-- Firebase SDKs -->
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getAuth, signInWithPopup, GoogleAuthProvider, createUserWithEmailAndPassword, updateProfile } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

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
        const provider = new GoogleAuthProvider();

        const signupForm = document.getElementById('signup-form');

        const nameInput = document.getElementById('name-input');
        const nameFeedback = document.getElementById('name-feedback');

        // Real-time Name Validation & Sanitization
        nameInput.addEventListener('input', function () {
            const originalVal = this.value;
            // Allow only letters and spaces
            const cleanVal = originalVal.replace(/[^a-zA-Z\s]/g, '');

            if (originalVal !== cleanVal) {
                this.value = cleanVal;
                nameFeedback.innerText = "Numbers and symbols are not allowed.";
                nameFeedback.style.display = 'block';
            } else {
                nameFeedback.style.display = 'none';
            }
        });

        // Real-time College ID Validation (Numbers only)
        const collegeIdInput = document.querySelector('input[name="college_id"]');
        if (collegeIdInput) {
            collegeIdInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        // Prevent leaving if empty or invalid range (Focus Trap - strict request)
        nameInput.addEventListener('blur', function () {
            if (this.value.trim().length > 0 && this.value.trim().length < 3) {
                nameFeedback.innerText = "Name is too short (min 3 chars).";
                nameFeedback.style.display = 'block';
                this.focus(); // Trap focus
            }
        });

        // Real-time Password Match check
        const pwdInput = signupForm.querySelector('input[name="password"]');
        const cpwdInput = signupForm.querySelector('input[name="confirm_password"]');
        const pwdFeedback = document.getElementById('password-feedback');

        const checkPasswords = () => {
            if (cpwdInput.value.length > 0) {
                if (pwdInput.value !== cpwdInput.value) {
                    pwdFeedback.style.display = 'block';
                } else {
                    pwdFeedback.style.display = 'none';
                }
            } else {
                pwdFeedback.style.display = 'none';
            }
        };

        pwdInput.addEventListener('input', checkPasswords);
        cpwdInput.addEventListener('input', checkPasswords);

        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const name = nameInput.value.trim();
            const email = signupForm.querySelector('input[name="email"]').value.trim();
            const passwordInput = signupForm.querySelector('input[name="password"]');
            const confirmPasswordInput = signupForm.querySelector('input[name="confirm_password"]');
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const btn = signupForm.querySelector('button[type="submit"]');

            const passwordFeedback = document.getElementById('password-feedback');

            // Clear previous errors
            const errorDiv = document.getElementById('js-error');
            const successDiv = document.getElementById('js-success');
            errorDiv.style.display = 'none';
            errorDiv.innerText = '';
            successDiv.style.display = 'none';
            successDiv.innerText = '';

            // Hide field level if valid (double check)
            if (/^[a-zA-Z\s]*$/.test(name)) {
                nameFeedback.style.display = 'none';
            }

            // --- Validations ---

            // 1. Name Validation
            const nameRegex = /^[a-zA-Z\s]+$/;
            const hasThreeRepeated = /(.)\1{2,}/i;
            const uniqueChars = new Set(name.replace(/\s/g, '').toLowerCase()).size;

            if (name.length < 3) {
                showError("Full name must be at least 3 characters long.");
                return;
            }
            if (!nameRegex.test(name)) {
                showError("Full name can only contain letters and spaces.");
                return;
            }
            if (hasThreeRepeated.test(name)) {
                showError("Name cannot contain 3 or more repeated characters in a row.");
                return;
            }
            if (uniqueChars < 2) {
                showError("Name must contain at least two different characters.");
                return;
            }
            // Vowel check for meaningless pattern
            const words = name.split(/\s+/);
            for (let w of words) {
                if (w.length >= 2 && !/[aeiou]/i.test(w)) {
                    showError("Please enter a valid, meaningful name.");
                    return;
                }
            }

            // 2. Email Validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError("Please enter a valid email address.");
                return;
            }

            // Domain Check
            const roleSelect = document.getElementById('role-select');
            const role = roleSelect.value;
            const isFaculty = (role === 'faculty');
            const isStudent = (role === 'student');

            if (email === 'sarath123@gmail.com') {
                // Admin bypass
            } else if (isFaculty && !email.endsWith('@amaljyothi.ac.in')) {
                showError("Faculty must use @amaljyothi.ac.in");
                return;
            } else if (isStudent && !email.endsWith('@mca.ajce.in')) {
                showError("Invalid email domain for students. Must use @mca.ajce.in");
                return;
            }

            // 3. Password Validation
            const pwdRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{6,}$/;
            if (!pwdRegex.test(password)) {
                showError("Password must be at least 6 characters, with uppercase, lowercase, number, and special character.");
                return;
            }
            if (password !== confirmPassword) {
                showError("Passwords do not match.");
                passwordFeedback.style.display = 'block';
                return;
            } else {
                passwordFeedback.style.display = 'none';
            }

            // 4. Student Fields Validation
            let college_id = "";
            let department = "";
            let year = "";
            if (isStudent) {
                college_id = signupForm.querySelector('input[name="college_id"]').value.trim();
                department = signupForm.querySelector('select[name="department"]').value;
                year = signupForm.querySelector('select[name="year"]').value;

                if (!college_id || !year) {
                    showError("Please fill all student details.");
                    return;
                }
                if (!/^\d+$/.test(college_id)) {
                    showError("College ID must be numeric.");
                    return;
                }
            }

            function showError(msg) {
                errorDiv.innerText = msg;
                errorDiv.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function showSuccess(msg) {
                successDiv.innerText = msg;
                successDiv.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            const originalText = btn.innerText;
            btn.innerText = "Creating Account...";
            btn.disabled = true;

            try {
                // 1. Create User in Firebase
                const userCredential = await createUserWithEmailAndPassword(auth, email, password);
                const user = userCredential.user;

                // 2. Update Profile Name
                await updateProfile(user, {
                    displayName: name
                });

                console.log("Firebase User Created & Profile Updated");

                // 3. Submit to PHP Backend
                const formData = new FormData();
                formData.append('name', name);
                formData.append('email', email);
                formData.append('password', password);
                formData.append('confirm_password', confirmPassword);
                formData.append('role', role);
                if (isStudent) {
                    formData.append('college_id', college_id);
                    formData.append('department', department);
                    formData.append('year', year);
                }

                const response = await fetch('', { // Post to self
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.message);
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showError(data.message);
                    btn.innerText = originalText;
                    btn.disabled = false;
                }

            } catch (error) {
                console.error("Signup Error:", error);

                if (error.code === 'auth/email-already-in-use') {
                    showError("Error: Email already associated with an account. Try logging in.");
                } else {
                    showError("Signup Failed: " + error.message);
                }
                btn.innerText = originalText;
                btn.disabled = false;
            }
        });

        // Google Sign In Logic (Existing)

        const googleBtn = document.querySelector('.btn-google');

        googleBtn.addEventListener('click', (e) => {
            e.preventDefault();

            if (googleBtn.disabled) return;
            googleBtn.disabled = true;
            googleBtn.style.opacity = "0.6";

            signInWithPopup(auth, provider)
                .then((result) => {
                    const user = result.user;
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
                    if (error.code !== 'auth/popup-closed-by-user' && error.code !== 'auth/cancelled-popup-request') {
                        alert("Google Sign-In failed: " + error.message);
                    }
                    googleBtn.disabled = false;
                    googleBtn.style.opacity = "1";
                });
        });

        // Toggle student fields based on role and email
        const roleSelect = document.getElementById('role-select');
        const emailInput = document.getElementById('email-input');
        const studentFields = document.getElementById('student-only-fields');

        const toggleFields = () => {
            const role = roleSelect.value;
            const cId = signupForm.querySelector('input[name="college_id"]');
            const dept = signupForm.querySelector('select[name="department"]');
            const yr = signupForm.querySelector('select[name="year"]');

            if (role === 'student') {
                studentFields.style.display = 'block';
                if (cId) cId.setAttribute('required', 'true');
                if (dept) dept.removeAttribute('required'); // Department is optional
                if (yr) yr.setAttribute('required', 'true');
            } else {
                studentFields.style.display = 'none';
                if (cId) cId.removeAttribute('required');
                if (dept) dept.removeAttribute('required');
                if (yr) yr.removeAttribute('required');
            }
        };

        roleSelect.addEventListener('change', toggleFields);
        emailInput.addEventListener('input', toggleFields);
        // Run once on load to set initial state
        toggleFields();
    </script>
</body>

</html>