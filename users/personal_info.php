<?php
require_once '../includes/db_config.php';
require_once '../includes/validation.php';

// Modify database table structure
try {
    $conn = getDBConnection();
    
    // Add email column if it doesn't exist
    $stmt = $conn->query("SHOW COLUMNS FROM personal_info LIKE 'email'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE personal_info ADD COLUMN email VARCHAR(255) AFTER phone_number");
    }
    
    // Remove occupation column if it exists
    $stmt = $conn->query("SHOW COLUMNS FROM personal_info LIKE 'occupation'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("ALTER TABLE personal_info DROP COLUMN occupation");
    }
} catch (PDOException $e) {
    // Log the error but continue with the script
    error_log("Database modification error: " . $e->getMessage());
}

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize all input data
    $data = sanitizeInput($_POST);
    
    // Validate the data
    $errors = validatePersonalInfo($data);

    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if personal info already exists
            $stmt = $conn->prepare("SELECT id FROM personal_info WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE personal_info SET 
                    first_name = ?, last_name = ?, phone_number = ?, 
                    email = ?, date_of_birth = ?, address = ?, gender = ?, 
                    nationality = ? 
                    WHERE user_id = ?");
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO personal_info 
                    (first_name, last_name, phone_number, email, date_of_birth, 
                    address, gender, nationality, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            }
            
            $stmt->execute([
                $data['first_name'], $data['last_name'], $data['phone_number'],
                $data['email'], $data['date_of_birth'], $data['address'],
                $data['gender'], $data['nationality'], $_SESSION['user_id']
            ]);
            
            $success = true;
            // Redirect to education page
            redirect('education.php');
        } catch (PDOException $e) {
            $errors[] = "Failed to save information: " . $e->getMessage();
        }
    }
}

// Fetch existing data if available
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM personal_info WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $personal_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch existing data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Information - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Personal Information</h3>
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
                                Information saved successfully! Redirecting...
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($personal_info['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($personal_info['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?php echo htmlspecialchars($personal_info['phone_number'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($personal_info['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($personal_info['date_of_birth'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($personal_info['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo ($personal_info['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($personal_info['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($personal_info['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="nationality" class="form-label">Nationality</label>
                                    <input type="text" class="form-control" id="nationality" name="nationality" 
                                           value="<?php echo htmlspecialchars($personal_info['nationality'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Save and Continue</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>