<?php
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skills = filter_input(INPUT_POST, 'skills', FILTER_SANITIZE_STRING);
    $certificate_path = null;

    // Validation
    if (empty($skills)) $errors[] = "Skills are required";

    // Handle certificate upload
    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        $cert_file = $_FILES['certificate'];
        $cert_ext = strtolower(pathinfo($cert_file['name'], PATHINFO_EXTENSION));
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array($cert_ext, $allowed_types)) {
            $errors[] = "Certificate must be a PDF, Word document, or image file (jpg, jpeg, png)";
        } else {
            $upload_dir = '../uploads/' . $_SESSION['user_id'];
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $certificate_path = $upload_dir . '/certificate.' . $cert_ext;
            if (!move_uploaded_file($cert_file['tmp_name'], $certificate_path)) {
                $errors[] = "Failed to upload certificate";
            }
        }
    }

    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if skills record already exists
            $stmt = $conn->prepare("SELECT id FROM skills WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE skills SET skills = ?, certificate_path = ? WHERE user_id = ?");
                $stmt->execute([$skills, $certificate_path, $_SESSION['user_id']]);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO skills (skills, certificate_path, user_id) VALUES (?, ?, ?)");
                $stmt->execute([$skills, $certificate_path, $_SESSION['user_id']]);
            }
            
            $success = true;
            // Redirect to documents page
            redirect('documents.php');
        } catch (PDOException $e) {
            $errors[] = "Failed to save information: " . $e->getMessage();
        }
    }
}

// Fetch existing data if available
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM skills WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $skills_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch existing data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Skills</h3>
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

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills</label>
                                <textarea class="form-control" id="skills" name="skills" rows="4" placeholder="Enter your skills (e.g., PHP, JavaScript, Communication, Teamwork)" required><?php echo htmlspecialchars($skills_data['skills'] ?? ''); ?></textarea>
                                <div class="form-text">Separate skills with commas</div>
                            </div>
                            <div class="mb-3">
                                <label for="certificate" class="form-label">Certificate (optional)</label>
                                <input type="file" class="form-control" id="certificate" name="certificate" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div class="form-text">Upload your certificate in PDF, Word, or image format</div>
                                <?php if (!empty($skills_data['certificate_path'])): ?>
                                    <div class="mt-2">
                                        <a href="<?php echo htmlspecialchars($skills_data['certificate_path']); ?>" target="_blank" class="btn btn-sm btn-info">View Current Certificate</a>
                                    </div>
                                <?php endif; ?>
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