<?php
require_once '../includes/db_config.php';
require_once '../includes/auto_shortlist.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

// Handle job application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    
    if ($job_id) {
        try {
            $conn = getDBConnection();
            
            // Check if already applied
            $stmt = $conn->prepare("SELECT id FROM job_applications WHERE user_id = ? AND job_id = ?");
            $stmt->execute([$_SESSION['user_id'], $job_id]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "You have already applied for this job.";
            } else {
                // Process automated shortlisting
                $result = processJobApplication($_SESSION['user_id'], $job_id);
                if ($result['status'] === 'error') {
                    $errors[] = "Error processing application: " . $result['reason'];
                } else {
                    $success = true;
                    $status_message = $result['status'] === 'shortlisted' ? 
                        "Your application has been shortlisted!" : 
                        ("Your application has been received but was not shortlisted. Reason: " . $result['reason']);
                    // Pass feedback via query string
                    header('Location: jobs.php?applied=1&status=' . urlencode($result['status']) . '&reason=' . urlencode($result['reason']));
                    exit;
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Failed to submit application: " . $e->getMessage();
        }
    }
}

// Fetch all active job advertisements with requirements and application counts
try {
    $conn = getDBConnection();
    
    // Fetch all active job advertisements with requirements and application counts
    $query = "SELECT 
        ja.*,
        jr.min_experience,
        jr.required_qualification,
        jr.required_skills,
        jr.min_cgpa,
        (SELECT COUNT(*) FROM job_applications WHERE job_id = ja.id) as total_applications,
        (SELECT COUNT(*) FROM job_applications WHERE job_id = ja.id AND status = 'shortlisted') as shortlisted_count,
        CASE WHEN ua.job_id IS NOT NULL THEN ua.status ELSE NULL END as application_status
    FROM job_advertisements ja
    LEFT JOIN job_requirements jr ON ja.id = jr.job_id
    LEFT JOIN job_applications ua ON ja.id = ua.job_id AND ua.user_id = ?
    WHERE ja.status = 'active' AND ja.expiry_date >= CURDATE()
    ORDER BY ja.posted_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errors[] = "Failed to fetch jobs: " . $e->getMessage();
}

if (isset($_GET['applied']) && $_GET['applied'] == 1): ?>
    <div class="alert alert-<?php echo (isset($_GET['status']) && $_GET['status'] === 'shortlisted') ? 'success' : 'warning'; ?> alert-dismissible fade show" role="alert">
        <?php if (isset($_GET['status']) && $_GET['status'] === 'shortlisted'): ?>
            Your application was submitted and shortlisted successfully!
        <?php else: ?>
            Your application was submitted but not shortlisted.<br>
            <strong>Reason:</strong> <?php echo htmlspecialchars($_GET['reason'] ?? 'Not specified'); ?>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Jobs - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .job-card {
            transition: transform 0.2s;
        }
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .requirements-list {
            list-style-type: none;
            padding-left: 0;
        }
        .requirements-list li {
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
            position: relative;
        }
        .requirements-list li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #0d6efd;
        }
    </style>
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="jobs.php">Available Jobs</a>
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
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $status_message ?? 'Your application has been submitted successfully!'; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo $error; ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h2 class="mb-4">Available Jobs</h2>
        
        <?php if (empty($jobs)): ?>
            <div class="alert alert-info">
                No active job advertisements available at the moment.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($jobs as $job): ?>
                    <div class="col">
                        <div class="card h-100 job-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <?php echo htmlspecialchars($job['company']); ?>
                                </h6>
                                
                                <div class="mb-3">
                                    <span class="badge bg-primary me-2">
                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($job['location']); ?>
                                    </span>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($job['job_type']); ?>
                                    </span>
                                </div>

                                <p class="card-text">
                                    <?php 
                                    $description = htmlspecialchars($job['description']);
                                    echo strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description;
                                    ?>
                                </p>

                                <?php if ($job['salary_range']): ?>
                                    <p class="card-text">
                                        <i class="bi bi-currency-dollar"></i> 
                                        <?php echo htmlspecialchars($job['salary_range']); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">
                                        Posted: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                    </small>
                                    <div>
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#jobModal<?php echo $job['id']; ?>">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Job Details Modal -->
                    <div class="modal fade" id="jobModal<?php echo $job['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h6>Company</h6>
                                            <p><?php echo htmlspecialchars($job['company']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Location</h6>
                                            <p><?php echo htmlspecialchars($job['location']); ?></p>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h6>Job Type</h6>
                                            <p><?php echo htmlspecialchars($job['job_type']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Salary Range</h6>
                                            <p><?php echo htmlspecialchars($job['salary_range']); ?></p>
                                        </div>
                                    </div>

                                    <h6>Job Description</h6>
                                    <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>

                                    <h6>Requirements</h6>
                                    <p><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>

                                    <h6>Shortlisting Criteria</h6>
                                    <ul class="requirements-list">
                                        <?php if ($job['min_experience']): ?>
                                            <li>Minimum Experience: <?php echo $job['min_experience']; ?> years</li>
                                        <?php endif; ?>
                                        <?php if ($job['required_qualification']): ?>
                                            <li>Required Qualification: <?php echo htmlspecialchars($job['required_qualification']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($job['required_skills']): ?>
                                            <li>Required Skills: <?php echo htmlspecialchars($job['required_skills']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($job['min_cgpa']): ?>
                                            <li>Minimum CGPA: <?php echo $job['min_cgpa']; ?></li>
                                        <?php endif; ?>
                                    </ul>

                                    <div class="alert alert-info">
                                        <small>
                                            <i class="bi bi-info-circle"></i>
                                            Total Applications: <?php echo $job['total_applications']; ?> |
                                            Shortlisted: <?php echo $job['shortlisted_count']; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <?php if ($job['application_status']): ?>
                                        <div class="alert alert-<?php echo $job['application_status'] === 'shortlisted' ? 'success' : 'warning'; ?> mb-0">
                                            Your application is <?php echo ucfirst($job['application_status']); ?>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <button type="submit" name="apply_job" class="btn btn-primary">
                                                Apply for this Job
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>