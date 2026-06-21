<?php
require_once '../includes/db_config.php';

// Check if user is logged in and is a manager
if (!isLoggedIn() || !isManager()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

// Handle job posting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_job'])) {
        $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
        if ($job_id) {
            try {
                $conn = getDBConnection();
                $conn->beginTransaction();

                // First delete related job applications
                $stmt = $conn->prepare("DELETE FROM job_applications WHERE job_id = ?");
                $stmt->execute([$job_id]);

                // Then delete job requirements
                $stmt = $conn->prepare("DELETE FROM job_requirements WHERE job_id = ?");
                $stmt->execute([$job_id]);

                // Finally delete the job advertisement
                $stmt = $conn->prepare("DELETE FROM job_advertisements WHERE id = ? AND manager_id = ?");
                $stmt->execute([$job_id, $_SESSION['user_id']]);

                $conn->commit();
                $success = true;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Failed to delete job: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_job'])) {
        $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $company = filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
        $job_type = filter_input(INPUT_POST, 'job_type', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $requirements = filter_input(INPUT_POST, 'requirements', FILTER_SANITIZE_STRING);
        $salary_range = filter_input(INPUT_POST, 'salary_range', FILTER_SANITIZE_STRING);
        $expiry_date = filter_input(INPUT_POST, 'expiry_date', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        // Job requirements for automated shortlisting
        $min_experience = filter_input(INPUT_POST, 'min_experience', FILTER_VALIDATE_INT);
        $required_qualification = filter_input(INPUT_POST, 'required_qualification', FILTER_SANITIZE_STRING);
        $required_skills = filter_input(INPUT_POST, 'required_skills', FILTER_SANITIZE_STRING);
        $min_cgpa = filter_input(INPUT_POST, 'min_cgpa', FILTER_VALIDATE_FLOAT);

        if ($job_id && $title && $company && $location && $job_type && $description && $requirements) {
            try {
                $conn = getDBConnection();
                $conn->beginTransaction();

                // Update job advertisement
                $stmt = $conn->prepare("UPDATE job_advertisements 
                    SET title = ?, company = ?, location = ?, job_type = ?, 
                        description = ?, requirements = ?, salary_range = ?, 
                        expiry_date = ?, status = ?
                    WHERE id = ? AND manager_id = ?");
                
                $stmt->execute([
                    $title, $company, $location, $job_type, $description, 
                    $requirements, $salary_range, $expiry_date, $status,
                    $job_id, $_SESSION['user_id']
                ]);

                // Update job requirements
                $stmt = $conn->prepare("UPDATE job_requirements 
                    SET min_experience = ?, required_qualification = ?, 
                        required_skills = ?, min_cgpa = ?
                    WHERE job_id = ?");
                
                $stmt->execute([
                    $min_experience, $required_qualification, 
                    $required_skills, $min_cgpa, $job_id
                ]);

                $conn->commit();
                $success = true;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Failed to update job: " . $e->getMessage();
            }
        } else {
            $errors[] = "All required fields must be filled out.";
        }
    } else {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $company = filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
        $job_type = filter_input(INPUT_POST, 'job_type', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $requirements = filter_input(INPUT_POST, 'requirements', FILTER_SANITIZE_STRING);
        $salary_range = filter_input(INPUT_POST, 'salary_range', FILTER_SANITIZE_STRING);
        $expiry_date = filter_input(INPUT_POST, 'expiry_date', FILTER_SANITIZE_STRING);

        // Job requirements for automated shortlisting
        $min_experience = filter_input(INPUT_POST, 'min_experience', FILTER_VALIDATE_INT);
        $required_qualification = filter_input(INPUT_POST, 'required_qualification', FILTER_SANITIZE_STRING);
        $required_skills = filter_input(INPUT_POST, 'required_skills', FILTER_SANITIZE_STRING);
        $min_cgpa = filter_input(INPUT_POST, 'min_cgpa', FILTER_VALIDATE_FLOAT);

        if ($title && $company && $location && $job_type && $description && $requirements) {
            try {
                $conn = getDBConnection();
                $conn->beginTransaction();

                // Insert job advertisement
                $stmt = $conn->prepare("INSERT INTO job_advertisements 
                    (title, company, location, job_type, description, requirements, salary_range, expiry_date, manager_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                
                $stmt->execute([
                    $title, $company, $location, $job_type, $description, 
                    $requirements, $salary_range, $expiry_date, $_SESSION['user_id']
                ]);

                $job_id = $conn->lastInsertId();

                // Insert job requirements
                $stmt = $conn->prepare("INSERT INTO job_requirements 
                    (job_id, min_experience, required_qualification, required_skills, min_cgpa) 
                    VALUES (?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $job_id, $min_experience, $required_qualification, 
                    $required_skills, $min_cgpa
                ]);

                $conn->commit();
                $success = true;
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Failed to post job: " . $e->getMessage();
            }
        } else {
            $errors[] = "All required fields must be filled out.";
        }
    }
}

// Fetch all job advertisements with their requirements
try {
    $conn = getDBConnection();
    $query = "SELECT ja.*, jr.min_experience, jr.required_qualification, 
                     jr.required_skills, jr.min_cgpa
              FROM job_advertisements ja 
              LEFT JOIN job_requirements jr ON ja.id = jr.job_id 
              WHERE ja.manager_id = ? 
              ORDER BY ja.posted_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch jobs: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Advertisements - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Manager Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="manager_dash.php">All Candidates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shortlisted.php">Shortlisted</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="jobs.php">Job Advertisements</a>
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
                Job advertisement has been posted successfully!
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

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Post New Job</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Job Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Company</label>
                                <input type="text" name="company" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Job Type</label>
                                <select name="job_type" class="form-select" required>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Internship">Internship</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Requirements</label>
                                <textarea name="requirements" class="form-control" rows="4" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Salary Range</label>
                                <input type="text" name="salary_range" class="form-control" placeholder="e.g., $50,000 - $70,000">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control" required>
                            </div>

                            <h5 class="mt-4">Automated Shortlisting Requirements</h5>
                            <div class="mb-3">
                                <label class="form-label">Minimum Experience (years)</label>
                                <input type="number" name="min_experience" class="form-control" min="0">
                            </div>

                            <div class="mb-3">
                                <label for="required_qualification" class="form-label">Required Qualifications</label>
                                <input type="text" class="form-control" id="required_qualification" name="required_qualification" placeholder="e.g. BS, MS, M.Phil, PhD">
                                <div class="form-text">Enter multiple qualifications separated by commas. Example: <code>BS, MS, M.Phil, PhD</code></div>
                            </div>

                            <div class="mb-3">
                                <label for="required_skills" class="form-label">Required Skills</label>
                                <input type="text" class="form-control" id="required_skills" name="required_skills" placeholder="e.g. PHP, JavaScript, Python">
                                <div class="form-text">Enter multiple skills separated by commas. Example: <code>PHP, JavaScript, Python</code></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Minimum CGPA</label>
                                <input type="number" name="min_cgpa" class="form-control" step="0.01" min="0" max="4">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Post Job</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Posted Jobs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($jobs)): ?>
                            <div class="alert alert-info">
                                No job advertisements posted yet.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($jobs as $job): ?>
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
                                        <p class="mb-1">
                                            <strong>Description:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Requirements:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                        </p>

                                        <?php if ($job['min_experience'] || $job['required_qualification'] || $job['required_skills'] || $job['min_cgpa']): ?>
                                            <p class="mb-1">
                                                <strong>Shortlisting Criteria:</strong><br>
                                                <?php if ($job['min_experience']): ?>
                                                    • Minimum Experience: <?php echo $job['min_experience']; ?> years<br>
                                                <?php endif; ?>
                                                <?php if ($job['required_qualification']): ?>
                                                    • Required Qualification: <?php echo htmlspecialchars($job['required_qualification']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($job['required_skills']): ?>
                                                    • Required Skills: <?php echo htmlspecialchars($job['required_skills']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($job['min_cgpa']): ?>
                                                    • Minimum CGPA: <?php echo $job['min_cgpa']; ?><br>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>

                                        <small class="text-muted">
                                            Expires: <?php echo date('M d, Y', strtotime($job['expiry_date'])); ?>
                                        </small>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php echo $job['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </span>
                                            <div class="btn-group ms-2">
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                        data-bs-target="#editJobModal<?php echo $job['id']; ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <form method="POST" action="" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this job advertisement?');">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" name="delete_job" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Job Modal -->
                                    <div class="modal fade" id="editJobModal<?php echo $job['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Job Advertisement</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Job Title</label>
                                                            <input type="text" name="title" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Company</label>
                                                            <input type="text" name="company" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($job['company']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Location</label>
                                                            <input type="text" name="location" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Job Type</label>
                                                            <select name="job_type" class="form-select" required>
                                                                <option value="Full-time" <?php echo $job['job_type'] === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                                                <option value="Part-time" <?php echo $job['job_type'] === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                                                <option value="Contract" <?php echo $job['job_type'] === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                                                <option value="Internship" <?php echo $job['job_type'] === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Requirements</label>
                                                            <textarea name="requirements" class="form-control" rows="4" required><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Salary Range</label>
                                                            <input type="text" name="salary_range" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($job['salary_range']); ?>" 
                                                                   placeholder="e.g., $50,000 - $70,000">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Expiry Date</label>
                                                            <input type="date" name="expiry_date" class="form-control" 
                                                                   value="<?php echo $job['expiry_date']; ?>" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Status</label>
                                                            <select name="status" class="form-select" required>
                                                                <option value="active" <?php echo $job['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="closed" <?php echo $job['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                            </select>
                                                        </div>

                                                        <h5 class="mt-4">Automated Shortlisting Requirements</h5>
                                                        <div class="mb-3">
                                                            <label class="form-label">Minimum Experience (years)</label>
                                                            <input type="number" name="min_experience" class="form-control" 
                                                                   value="<?php echo $job['min_experience']; ?>" min="0">
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="required_qualification" class="form-label">Required Qualifications</label>
                                                            <input type="text" class="form-control" id="required_qualification" name="required_qualification" placeholder="e.g. BS, MS, M.Phil, PhD" value="<?php echo htmlspecialchars($job['required_qualification'] ?? ''); ?>">
                                                            <div class="form-text">Enter multiple qualifications separated by commas. Example: <code>BS, MS, M.Phil, PhD</code></div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="required_skills" class="form-label">Required Skills</label>
                                                            <input type="text" class="form-control" id="required_skills" name="required_skills" placeholder="e.g. PHP, JavaScript, Python" value="<?php echo htmlspecialchars($job['required_skills'] ?? ''); ?>">
                                                            <div class="form-text">Enter multiple skills separated by commas. Example: <code>PHP, JavaScript, Python</code></div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Minimum CGPA</label>
                                                            <input type="number" name="min_cgpa" class="form-control" 
                                                                   value="<?php echo $job['min_cgpa']; ?>" step="0.01" min="0" max="4">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" name="update_job" class="btn btn-primary">Update Job</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <script>
        new Tagify(document.querySelector('#required_qualification'));
        new Tagify(document.querySelector('#required_skills'));

        document.querySelector('form').addEventListener('submit', function(e) {
            // For qualifications
            var qualInput = document.querySelector('#required_qualification');
            if (qualInput && qualInput.value.startsWith('[')) {
                var qualArr = JSON.parse(qualInput.value);
                qualInput.value = qualArr.map(function(item) { return item.value; }).join(', ');
            }
            // For skills
            var skillInput = document.querySelector('#required_skills');
            if (skillInput && skillInput.value.startsWith('[')) {
                var skillArr = JSON.parse(skillInput.value);
                skillInput.value = skillArr.map(function(item) { return item.value; }).join(', ');
            }
        });
    </script>
</body>
</html> 