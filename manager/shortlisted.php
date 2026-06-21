<?php
require_once '../includes/db_config.php';

// Check if user is logged in and is a manager
if (!isLoggedIn() || !isManager()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

// Handle shortlisting action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $status = strtolower(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING));
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);

    if ($user_id && $status) {
        try {
            $conn = getDBConnection();
            
            // Check if shortlisting record exists
            $stmt = $conn->prepare("SELECT id FROM shortlisting WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE shortlisting SET 
                    status = ?, comments = ?, manager_id = ? 
                    WHERE user_id = ?");
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO shortlisting 
                    (status, comments, manager_id, user_id) 
                    VALUES (?, ?, ?, ?)");
            }
            
            $stmt->execute([$status, $comments, $_SESSION['user_id'], $user_id]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Failed to update shortlisting status: " . $e->getMessage();
        }
    }
}

// Fetch shortlisted candidates with merit calculation
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
        sl.status as shortlist_status,
        sl.comments as shortlist_comments,
        sl.shortlisted_date,
        -- Calculate merit score
        (
            -- Qualification points (M.Phil = 4, BS/BSc = 3, FSc/ICS = 2, Matric = 1)
            CASE 
                WHEN e.qualification LIKE '%M.Phil%' THEN 4
                WHEN e.qualification LIKE '%BS%' OR e.qualification LIKE '%BSc%' THEN 3
                WHEN e.qualification LIKE '%FSc%' OR e.qualification LIKE '%ICS%' THEN 2
                WHEN e.qualification LIKE '%Matric%' THEN 1
                ELSE 0
            END +
            -- CGPA points (4.0 = 4, 3.5-3.99 = 3, 3.0-3.49 = 2, 2.5-2.99 = 1)
            CASE 
                WHEN e.percentage_cgpa >= 4.0 THEN 4
                WHEN e.percentage_cgpa >= 3.5 THEN 3
                WHEN e.percentage_cgpa >= 3.0 THEN 2
                WHEN e.percentage_cgpa >= 2.5 THEN 1
                ELSE 0
            END +
            -- Skills points (1 point per required skill)
            (
                CASE WHEN s.skills LIKE '%PHP%' THEN 1 ELSE 0 END +
                CASE WHEN s.skills LIKE '%Python%' THEN 1 ELSE 0 END +
                CASE WHEN s.skills LIKE '%JavaScript%' THEN 1 ELSE 0 END +
                CASE WHEN s.skills LIKE '%HTML%' THEN 1 ELSE 0 END +
                CASE WHEN s.skills LIKE '%CSS%' THEN 1 ELSE 0 END
            ) +
            -- Experience points (1 point per year, max 5)
            LEAST(COALESCE(exp.years_of_experience, 0), 5)
        ) as merit_score
    FROM users u
    LEFT JOIN personal_info pi ON u.id = pi.user_id
    LEFT JOIN education e ON u.id = e.user_id
    LEFT JOIN experience exp ON u.id = exp.user_id
    LEFT JOIN skills s ON u.id = s.user_id
    LEFT JOIN documents d ON u.id = d.user_id
    LEFT JOIN shortlisting sl ON u.id = sl.user_id
    WHERE u.role = 'candidate' AND LOWER(sl.status) = 'shortlisted'
    ORDER BY merit_score DESC, sl.shortlisted_date DESC";
    
    $stmt = $conn->query($query);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errors[] = "Failed to fetch candidates: " . $e->getMessage();
}

if ($success) {
    header('Location: shortlisted.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlisted Candidates - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .merit-badge {
            font-size: 0.9em;
            padding: 0.35em 0.65em;
        }
        .merit-score {
            font-weight: bold;
            color: #198754;
        }
        .merit-details {
            font-size: 0.85em;
            color: #666;
        }
    </style>
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
                        <a class="nav-link active" href="shortlisted.php">Shortlisted</a>
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

        <?php if ($success): ?>
            <div class="alert alert-success">
                Shortlisting status updated successfully!
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Shortlisted Candidates (Ranked by Merit)</h5>
                        <span class="badge bg-success"><?php echo count($candidates); ?> candidates</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($candidates)): ?>
                            <div class="alert alert-info">
                                No shortlisted candidates found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Merit Rank</th>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>Education</th>
                                            <th>Experience</th>
                                            <th>Skills</th>
                                            <th>Documents</th>
                                            <th>Shortlisted Date</th>
                                            <th>Comments</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rank = 1;
                                        foreach ($candidates as $candidate): 
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="merit-score">#<?php echo $rank++; ?></span>
                                                <div class="merit-details">
                                                    Score: <?php echo $candidate['merit_score']; ?>/13
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($candidate['email']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($candidate['phone_number']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($candidate['qualification']); ?><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($candidate['institute']); ?><br>
                                                    CGPA: <?php echo htmlspecialchars($candidate['percentage_cgpa']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($candidate['years_of_experience']); ?> years<br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($candidate['previous_job_title']); ?><br>
                                                    <?php echo htmlspecialchars($candidate['company_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                        data-bs-target="#skillsModal<?php echo $candidate['user_id']; ?>">
                                                    View Skills
                                                </button>
                                            </td>
                                            <td>
                                                <?php if ($candidate['cv_path']): ?>
                                                <a href="<?php echo htmlspecialchars($candidate['cv_path']); ?>" target="_blank" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-file-earmark-text"></i> CV
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($candidate['certificates_path']): ?>
                                                <a href="<?php echo htmlspecialchars($candidate['certificates_path']); ?>" target="_blank" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-file-earmark-text"></i> Certificates
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($candidate['shortlisted_date'])); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($candidate['shortlist_comments'] ?? 'No comments'); ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                        data-bs-target="#shortlistModal<?php echo $candidate['user_id']; ?>">
                                                    Update Status
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Skills Modal -->
                                        <div class="modal fade" id="skillsModal<?php echo $candidate['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Skills</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><?php echo nl2br(htmlspecialchars($candidate['skills'])); ?></p>
                                                        <?php if (!empty($candidate['certificate_path'])): ?>
                                                            <div class="mt-2">
                                                                <a href="<?php echo htmlspecialchars($candidate['certificate_path']); ?>" target="_blank" class="btn btn-sm btn-info">View Certificate</a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Shortlist Modal -->
                                        <div class="modal fade" id="shortlistModal<?php echo $candidate['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Candidate Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="user_id" value="<?php echo $candidate['user_id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select name="status" class="form-select" required>
                                                                    <option value="shortlisted" selected>Shortlisted</option>
                                                                    <option value="rejected">Rejected</option>
                                                                    <option value="pending">Pending</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Comments</label>
                                                                <textarea name="comments" class="form-control" rows="3"><?php echo htmlspecialchars($candidate['shortlist_comments'] ?? ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="action" value="shortlist" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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