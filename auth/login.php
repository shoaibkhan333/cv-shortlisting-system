<?php
require_once '../includes/db_config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Enhanced Email Validation
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (!preg_match('/@gmail\.com$/', $email)) {
        $errors[] = "Only Gmail addresses are allowed (@gmail.com)";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    }

    // If no errors, proceed with login
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ? AND role = 'candidate'");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = 'candidate';
                    redirect('../users/dashboard.php');
                } else {
                    $errors[] = "Invalid password";
                }
            } else {
                $errors[] = "Candidate account not found";
            }
        } catch (PDOException $e) {
            $errors[] = "Login failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Login - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-text {
            font-size: 0.9em;
            color: #666;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Candidate Login</h3>
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

                        <form method="POST" action="" id="loginForm" onsubmit="return validateForm()">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       pattern="[a-z0-9._%+-]+@gmail\.com$" 
                                       title="Please enter a valid Gmail address">
                                <div class="form-text">Only Gmail addresses are allowed</div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login as Candidate</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p>Don't have an account? <a href="signup.php">Register here</a></p>
                            <p>Are you a manager? <a href="manager_login.php">Login as Manager</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            // Validate Gmail address
            if (!email.endsWith('@gmail.com')) {
                alert('Please use a Gmail address');
                return false;
            }

            // Validate password is not empty
            if (password.trim() === '') {
                alert('Password is required');
                return false;
            }

            return true;
        }

        // Add real-time email validation
        document.getElementById('email').addEventListener('input', function(e) {
            const email = e.target.value;
            const formText = e.target.nextElementSibling;
            
            if (email && !email.endsWith('@gmail.com')) {
                formText.className = 'form-text text-danger';
                formText.textContent = 'Please use a Gmail address';
            } else {
                formText.className = 'form-text';
                formText.textContent = 'Only Gmail addresses are allowed';
            }
        });
    </script>
</body>
</html>