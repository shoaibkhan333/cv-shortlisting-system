<?php
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/' . $_SESSION['user_id'];
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle CV upload
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $cv_file = $_FILES['cv'];
        $cv_ext = strtolower(pathinfo($cv_file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx'];
        if (!in_array($cv_ext, $allowed_types)) {
            $errors[] = "CV must be a PDF or Word document";
        } else {
            $cv_path = $upload_dir . '/cv.' . $cv_ext;
            if (!move_uploaded_file($cv_file['tmp_name'], $cv_path)) {
                $errors[] = "Failed to upload CV";
            }
        }
    }

    // Handle certificates upload
    $certificates_path = null;
    if (isset($_FILES['certificates']) && $_FILES['certificates']['error'] === UPLOAD_ERR_OK) {
        $cert_file = $_FILES['certificates'];
        $cert_ext = strtolower(pathinfo($cert_file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($cert_ext, $allowed_types)) {
            $errors[] = "Certificates must be PDF or image files";
        } else {
            $certificates_path = $upload_dir . '/certificates.' . $cert_ext;
            if (!move_uploaded_file($cert_file['tmp_name'], $certificates_path)) {
                $errors[] = "Failed to upload certificates";
            }
        }
    }

    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if documents record already exists
            $stmt = $conn->prepare("SELECT id FROM documents WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE documents SET 
                    cv_path = ?, certificates_path = ? 
                    WHERE user_id = ?");
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO documents 
                    (cv_path, certificates_path, user_id) 
                    VALUES (?, ?, ?)");
            }
            
            $stmt->execute([
                $cv_path ?? null, $certificates_path, $_SESSION['user_id']
            ]);
            
            $success = true;
            // Redirect to dashboard
            redirect('dashboard.php');
        } catch (PDOException $e) {
            $errors[] = "Failed to save information: " . $e->getMessage();
        }
    }
}

// Fetch existing data if available
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM documents WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $documents = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch existing data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Upload Documents</h3>
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
                                Documents uploaded successfully! Redirecting...
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="cv" class="form-label">CV/Resume</label>
                                <input type="file" class="form-control" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                                <div class="form-text">Upload your CV in PDF or Word format</div>
                                <?php if (!empty($documents['cv_path'])): ?>
                                    <div class="mt-2">
                                        <a href="<?php echo htmlspecialchars($documents['cv_path']); ?>" target="_blank" class="btn btn-sm btn-info">View Current CV</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="certificates" class="form-label">Certificates</label>
                                <input type="file" class="form-control" id="certificates" name="certificates" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text">Upload your certificates in PDF or image format</div>
                                <?php if (!empty($documents['certificates_path'])): ?>
                                    <div class="mt-2">
                                        <a href="<?php echo htmlspecialchars($documents['certificates_path']); ?>" target="_blank" class="btn btn-sm btn-info">View Current Certificates</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Upload and Complete</button>
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