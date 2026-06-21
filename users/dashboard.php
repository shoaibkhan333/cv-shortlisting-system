<?php
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$errors = [];
$documents = null;
$shortlisting = null;

// Fetch user's complete profile
try {
    $conn = getDBConnection();
    
    // Get personal information
    $stmt = $conn->prepare("SELECT * FROM personal_info WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $personal_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get education (use year_of_completion)
    $stmt = $conn->prepare("SELECT * FROM education WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $education = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get experience
    $stmt = $conn->prepare("SELECT * FROM experience WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $experience = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get skills
    $stmt = $conn->prepare("SELECT * FROM skills WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get documents
    $stmt = $conn->prepare("SELECT * FROM documents WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $documents = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get shortlisting status
    $stmt = $conn->prepare("SELECT * FROM shortlisting WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $shortlisting = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errors[] = "Failed to fetch profile data: " . $e->getMessage();
}

// Fetch user's job applications and their status
try {
    $conn = getDBConnection();
    $query = "SELECT ja.*, jaa.status as application_status, jaa.application_date
              FROM job_advertisements ja
              LEFT JOIN job_applications jaa ON ja.id = jaa.job_id AND jaa.user_id = ?
              WHERE ja.status = 'active' AND ja.expiry_date >= CURDATE()
              ORDER BY ja.posted_date DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $recent_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch recent jobs: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">CV Shortlisting System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">Available Jobs</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    $message = match($_GET['success']) {
                        'profile' => 'Profile information updated successfully!',
                        'education' => 'Education information updated successfully!',
                        'experience' => 'Experience information updated successfully!',
                        'skills' => 'Skills information updated successfully!',
                        'documents' => 'Documents uploaded successfully!',
                        default => 'Operation completed successfully!'
                    };
                    echo htmlspecialchars($message);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Profile Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="personal_info.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Personal Information
                                <i class="bi bi-<?php echo $personal_info ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
                            </a>
                            <a href="education.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Education
                                <i class="bi bi-<?php echo !empty($education) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
                            </a>
                            <a href="experience.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Experience
                                <i class="bi bi-<?php echo !empty($experience) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
                            </a>
                            <a href="skills.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Skills
                                <i class="bi bi-<?php echo !empty($skills) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
                            </a>
                            <a href="documents.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Documents
                                <i class="bi bi-<?php echo $documents ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($shortlisting): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Application Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-<?php 
                            echo $shortlisting['status'] === 'shortlisted' ? 'success' : 
                                ($shortlisting['status'] === 'rejected' ? 'danger' : 'warning'); 
                        ?>">
                            <h6 class="alert-heading">Status: <?php echo ucfirst($shortlisting['status']); ?></h6>
                            <?php if ($shortlisting['comments']): ?>
                                <p class="mb-0"><?php echo htmlspecialchars($shortlisting['comments']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Profile Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($personal_info): ?>
                        <h6 class="mb-3">Personal Information</h6>
                        <p>
                            <strong>Name:</strong> <?php echo htmlspecialchars($personal_info['first_name'] . ' ' . $personal_info['last_name']); ?><br>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($personal_info['phone_number']); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($personal_info['email']); ?><br>
                            <strong>Nationality:</strong> <?php echo htmlspecialchars($personal_info['nationality']); ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($education)): ?>
                        <h6 class="mb-3 mt-4">Education</h6>
                        <div class="mb-3">
                            <strong>Qualification:</strong> <?php echo htmlspecialchars($education['qualification']); ?><br>
                            <strong>Institute:</strong> <?php echo htmlspecialchars($education['institute']); ?><br>
                            <strong>Year of Completion:</strong> <?php echo htmlspecialchars($education['year_of_completion']); ?><br>
                            <strong>Percentage/CGPA:</strong> <?php echo htmlspecialchars($education['percentage_cgpa']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($experience)): ?>
                        <h6 class="mb-3 mt-4">Experience</h6>
                        <?php foreach ($experience as $exp): ?>
                        <div class="mb-3">
                            <strong>Years of Experience:</strong> <?php echo htmlspecialchars($exp['years_of_experience']); ?><br>
                            <strong>Previous Job Title:</strong> <?php echo htmlspecialchars($exp['previous_job_title']); ?><br>
                            <strong>Company Name:</strong> <?php echo htmlspecialchars($exp['company_name']); ?><br>
                            <strong>Responsibilities:</strong><br>
                            <?php echo nl2br(htmlspecialchars($exp['responsibilities'])); ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($skills)): ?>
                        <h6 class="mb-3 mt-4">Skills</h6>
                        <div class="mb-3">
                            <?php echo htmlspecialchars($skills[0]['skills'] ?? ''); ?>
                            <?php if (!empty($skills[0]['certificate_path'])): ?>
                                <div class="mt-2">
                                    <a href="<?php echo htmlspecialchars($skills[0]['certificate_path']); ?>" target="_blank" class="btn btn-sm btn-info">View Certificate</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($documents): ?>
                        <h6 class="mb-3 mt-4">Documents</h6>
                        <p>
                            <?php if ($documents['cv_path']): ?>
                            <a href="<?php echo htmlspecialchars($documents['cv_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-file-earmark-text"></i> View CV
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($documents['certificates_path']): ?>
                            <a href="<?php echo htmlspecialchars($documents['certificates_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-file-earmark-text"></i> View Certificates
                            </a>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Job Opportunities</h5>
                        <a href="jobs.php" class="btn btn-primary btn-sm">View All Jobs</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_jobs)): ?>
                            <div class="alert alert-info">
                                No job positions are currently available.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_jobs as $job): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                            <small class="text-muted">
                                                Posted: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                            </small>
                                        </div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($job['company']); ?></h6>
                                        <p class="mb-1">
                                            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($job['location']); ?> |
                                            <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($job['job_type']); ?>
                                            <?php if ($job['salary_range']): ?>
                                                | <i class="bi bi-currency-dollar"></i> <?php echo htmlspecialchars($job['salary_range']); ?>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <?php if ($job['application_status']): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-<?php 
                                                    echo $job['application_status'] === 'accepted' ? 'success' : 
                                                        ($job['application_status'] === 'rejected' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($job['application_status']); ?>
                                                </span>
                                                <small class="text-muted ms-2">
                                                    Applied on: <?php echo date('M d, Y', strtotime($job['application_date'])); ?>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <a href="apply_job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary btn-sm mt-2">Apply Now</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 