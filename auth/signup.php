<?php
require_once '../includes/db_config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Enhanced Email Validation
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (!preg_match('/@gmail\.com$/', $email)) {
        $errors[] = "Only Gmail addresses are allowed (@gmail.com)";
    }

    // Enhanced Password Validation
    if (empty($password)) {
        $errors[] = "Password is required";
    } else {
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&*()-_=+{};:,<.>)";
        }
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already registered";
            } else {
                // Hash password and insert candidate
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'candidate')");
                $stmt->execute([$email, $hashed_password]);
                
                $success = true;
                // Redirect to personal information page
                $_SESSION['user_id'] = $conn->lastInsertId();
                $_SESSION['role'] = 'candidate';
                redirect('login.php');
            }
        } catch (PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Registration - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .password-requirements {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .requirement {
            margin-bottom: 3px;
        }
        .requirement.met {
            color: #198754;
        }
        .requirement.not-met {
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Candidate Registration</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                Registration successful! Redirecting...
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="registrationForm" onsubmit="return validateForm()">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       pattern="[a-z0-9._%+-]+@gmail\.com$" 
                                       title="Please enter a valid Gmail address">
                                <div class="form-text">Only Gmail addresses are allowed</div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required
                                       minlength="8">
                                <div class="password-requirements">
                                    <div class="requirement" id="length">At least 8 characters long</div>
                                    <div class="requirement" id="uppercase">Contains uppercase letter</div>
                                    <div class="requirement" id="lowercase">Contains lowercase letter</div>
                                    <div class="requirement" id="number">Contains number</div>
                                    <div class="requirement" id="special">Contains special character</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div id="passwordMatch" class="form-text"></div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Register as Candidate</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <p>Are you a manager? <a href="manager_login.php">Login as Manager</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        const requirements = {
            length: document.getElementById('length'),
            uppercase: document.getElementById('uppercase'),
            lowercase: document.getElementById('lowercase'),
            number: document.getElementById('number'),
            special: document.getElementById('special')
        };

        function updateRequirement(element, met) {
            element.classList.remove('met', 'not-met');
            element.classList.add(met ? 'met' : 'not-met');
        }

        function validatePassword() {
            const value = password.value;
            updateRequirement(requirements.length, value.length >= 8);
            updateRequirement(requirements.uppercase, /[A-Z]/.test(value));
            updateRequirement(requirements.lowercase, /[a-z]/.test(value));
            updateRequirement(requirements.number, /[0-9]/.test(value));
            updateRequirement(requirements.special, /[!@#$%^&*()\-_=+{};:,<.>]/.test(value));
        }

        function checkPasswordMatch() {
            if (confirmPassword.value === '') {
                passwordMatch.textContent = '';
                passwordMatch.className = 'form-text';
            } else if (password.value === confirmPassword.value) {
                passwordMatch.textContent = 'Passwords match';
                passwordMatch.className = 'form-text text-success';
            } else {
                passwordMatch.textContent = 'Passwords do not match';
                passwordMatch.className = 'form-text text-danger';
            }
        }

        function validateForm() {
            const email = document.getElementById('email').value;
            if (!email.endsWith('@gmail.com')) {
                alert('Please use a Gmail address');
                return false;
            }

            if (password.value !== confirmPassword.value) {
                alert('Passwords do not match');
                return false;
            }

            // Check all password requirements
            if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[!@#$%^&*()\-_=+{};:,<.>]).{8,}$/.test(password.value)) {
                alert('Password does not meet all requirements');
                return false;
            }

            return true;
        }

        password.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html>