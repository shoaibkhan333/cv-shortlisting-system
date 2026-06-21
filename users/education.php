<?php
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qualification = filter_input(INPUT_POST, 'qualification', FILTER_SANITIZE_STRING);
    $institute = filter_input(INPUT_POST, 'institute', FILTER_SANITIZE_STRING);
    $year_of_completion = filter_input(INPUT_POST, 'year_of_completion', FILTER_VALIDATE_INT);
    $percentage_cgpa = filter_input(INPUT_POST, 'percentage_cgpa', FILTER_VALIDATE_FLOAT);

    // Validation
    if (empty($qualification)) $errors[] = "Qualification is required";
    if (empty($institute)) $errors[] = "Institute is required";
    if (empty($year_of_completion)) $errors[] = "Year of completion is required";
    if ($year_of_completion < 1900 || $year_of_completion > date('Y')) {
        $errors[] = "Invalid year of completion";
    }
    if (empty($percentage_cgpa)) $errors[] = "Percentage/CGPA is required";
    if ($percentage_cgpa < 0 || $percentage_cgpa > 100) {
        $errors[] = "Percentage/CGPA must be between 0 and 100";
    }

    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if education record already exists
            $stmt = $conn->prepare("SELECT id FROM education WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE education SET 
                    qualification = ?, institute = ?, year_of_completion = ?, 
                    percentage_cgpa = ? WHERE user_id = ?");
                $stmt->execute([
                    $qualification, $institute, $year_of_completion,
                    $percentage_cgpa, $_SESSION['user_id']
                ]);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO education 
                    (qualification, institute, year_of_completion, percentage_cgpa, user_id) 
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $qualification, $institute, $year_of_completion,
                    $percentage_cgpa, $_SESSION['user_id']
                ]);
            }
            
            $success = true;
            // Redirect to experience page
            redirect('experience.php');
        } catch (PDOException $e) {
            $errors[] = "Failed to save information: " . $e->getMessage();
        }
    }
}

// Fetch existing data if available
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM education WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $education = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch existing data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educational Background - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Educational Background</h3>
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
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" 
                                       value="<?php echo htmlspecialchars($education['qualification'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="institute" class="form-label">Institute</label>
                                <input type="text" class="form-control" id="institute" name="institute" 
                                       value="<?php echo htmlspecialchars($education['institute'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="year_of_completion" class="form-label">Year of Completion</label>
                                <input type="number" class="form-control" id="year_of_completion" name="year_of_completion" 
                                       min="1900" max="<?php echo date('Y'); ?>" 
                                       value="<?php echo htmlspecialchars($education['year_of_completion'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="percentage_cgpa" class="form-label">Percentage/CGPA</label>
                                <input type="number" class="form-control" id="percentage_cgpa" name="percentage_cgpa" 
                                       min="0" max="100" step="0.01" 
                                       value="<?php echo htmlspecialchars($education['percentage_cgpa'] ?? ''); ?>" required>
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