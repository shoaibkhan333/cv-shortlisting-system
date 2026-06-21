<?php
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $years_of_experience = filter_input(INPUT_POST, 'years_of_experience', FILTER_VALIDATE_INT);
    $previous_job_title = filter_input(INPUT_POST, 'previous_job_title', FILTER_SANITIZE_STRING);
    $company_name = filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_STRING);
    $responsibilities = filter_input(INPUT_POST, 'responsibilities', FILTER_SANITIZE_STRING);

    // Validation
    if (empty($years_of_experience)) $errors[] = "Years of experience is required";
    if ($years_of_experience < 0) $errors[] = "Years of experience cannot be negative";
    if (empty($previous_job_title)) $errors[] = "Previous job title is required";
    if (empty($company_name)) $errors[] = "Company name is required";
    if (empty($responsibilities)) $errors[] = "Responsibilities are required";

    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if experience record already exists
            $stmt = $conn->prepare("SELECT id FROM experience WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE experience SET 
                    years_of_experience = ?, previous_job_title = ?, company_name = ?, 
                    responsibilities = ? WHERE user_id = ?");
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO experience 
                    (years_of_experience, previous_job_title, company_name, responsibilities, user_id) 
                    VALUES (?, ?, ?, ?, ?)");
            }
            
            $stmt->execute([
                $years_of_experience, $previous_job_title, $company_name,
                $responsibilities, $_SESSION['user_id']
            ]);
            
            $success = true;
            // Redirect to skills page
            redirect('skills.php');
        } catch (PDOException $e) {
            $errors[] = "Failed to save information: " . $e->getMessage();
        }
    }
}

// Fetch existing data if available
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM experience WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $experience = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch existing data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Experience - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Work Experience</h3>
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
                            <div class="mb-3">
                                <label for="years_of_experience" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="years_of_experience" name="years_of_experience" 
                                       min="0" value="<?php echo htmlspecialchars($experience['years_of_experience'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="previous_job_title" class="form-label">Previous Job Title</label>
                                <input type="text" class="form-control" id="previous_job_title" name="previous_job_title" 
                                       value="<?php echo htmlspecialchars($experience['previous_job_title'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($experience['company_name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="responsibilities" class="form-label">Responsibilities</label>
                                <textarea class="form-control" id="responsibilities" name="responsibilities" rows="4" required><?php echo htmlspecialchars($experience['responsibilities'] ?? ''); ?></textarea>
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