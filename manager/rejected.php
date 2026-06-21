<?php
require_once '../includes/db_config.php';

// Check if user is logged in and is a manager
if (!isLoggedIn() || !isManager()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

// Fetch rejected candidates
try {
    $conn = getDBConnection();
    
    $query = "SELECT 
        u.id as user_id,
        u.email,
        pi.first_name,
        pi.last_name,
        pi.phone_number,
        e.qualification,
        e.institute,
        e.percentage_cgpa,
        exp.years_of_experience,
        exp.previous_job_title,
        exp.company_name,
        s.skills,
        s.certificate_path,
        d.cv_path,
        d.certificates_path,
        ja.job_id,
        ja.status,
        ja.shortlist_reason,
        ja.application_date,
        jaa.title as job_title,
        jaa.company as job_company
    FROM users u
    LEFT JOIN personal_info pi ON u.id = pi.user_id
    LEFT JOIN education e ON u.id = e.user_id
    LEFT JOIN experience exp ON u.id = exp.user_id
    LEFT JOIN skills s ON u.id = s.user_id
    LEFT JOIN documents d ON u.id = d.user_id
    LEFT JOIN job_applications ja ON u.id = ja.user_id
    LEFT JOIN job_advertisements jaa ON ja.job_id = jaa.id
    WHERE u.role = 'candidate' AND ja.status = 'rejected'
    ORDER BY ja.application_date DESC";
    
    $stmt = $conn->query($query);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errors[] = "Failed to fetch candidates: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected Candidates - CV Shortlisting System</title>
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
                        <a class="nav-link" href="manager_dash.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">Job Advertisements</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shortlisted.php">Shortlisted</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="rejected.php">Rejected</a>
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
        <h2 class="mb-4">Rejected Candidates</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo $error; ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($candidates)): ?>
            <div class="alert alert-info">
                No rejected candidates found.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Job Applied</th>
                            <th>Rejection Reason</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($candidate['email']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($candidate['job_title']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($candidate['job_company']); ?></small>
                                </td>
                                <td>
                                    <?php 
                                        echo isset($candidate['shortlist_reason']) && $candidate['shortlist_reason'] !== '' 
                                            ? htmlspecialchars($candidate['shortlist_reason']) 
                                            : 'No reason provided'; 
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($candidate['application_date'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#candidateModal<?php echo $candidate['user_id']; ?>">
                                        View Profile
                                    </button>
                                </td>
                            </tr>

                            <!-- Candidate Profile Modal -->
                            <div class="modal fade" id="candidateModal<?php echo $candidate['user_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <h6>Personal Information</h6>
                                            <p>
                                                <strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?><br>
                                                <strong>Phone:</strong> <?php echo htmlspecialchars($candidate['phone_number']); ?><br>
                                            </p>
                                            <h6>Education</h6>
                                            <p>
                                                <strong>Qualification:</strong> <?php echo htmlspecialchars($candidate['qualification']); ?><br>
                                                <strong>Institute:</strong> <?php echo htmlspecialchars($candidate['institute']); ?><br>
                                                <strong>CGPA/Percentage:</strong> <?php echo htmlspecialchars($candidate['percentage_cgpa']); ?>
                                            </p>
                                            <h6>Experience</h6>
                                            <p>
                                                <strong>Years of Experience:</strong> <?php echo htmlspecialchars($candidate['years_of_experience']); ?><br>
                                                <strong>Previous Job:</strong> <?php echo htmlspecialchars($candidate['previous_job_title']); ?><br>
                                                <strong>Company:</strong> <?php echo htmlspecialchars($candidate['company_name']); ?>
                                            </p>
                                            <h6>Skills</h6>
                                            <p>
                                                <?php echo nl2br(htmlspecialchars($candidate['skills'])); ?>
                                                <?php if (!empty($candidate['certificate_path'])): ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($candidate['certificate_path']); ?>" target="_blank" class="btn btn-sm btn-info">View Certificate</a>
                                                    </div>
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($candidate['cv_path']): ?>
                                                <h6>Documents</h6>
                                                <p>
                                                    <a href="<?php echo htmlspecialchars($candidate['cv_path']); ?>" 
                                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                                        View CV
                                                    </a>
                                                    <?php if ($candidate['certificates_path']): ?>
                                                        <a href="<?php echo htmlspecialchars($candidate['certificates_path']); ?>" 
                                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                                            View Certificates
                                                        </a>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 