<?php
require_once '../includes/db_config.php';

// Check if user is logged in and is a manager
if (!isLoggedIn() || !isManager()) {
    redirect('../auth/login.php');
}

$errors = [];
$success = false;

// Handle candidate deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_candidate'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if ($user_id) {
        try {
            $conn = getDBConnection();
            $conn->beginTransaction();

            // Delete related records first
            $tables = [
                'job_applications',
                'documents',
                'skills',
                'experience',
                'education',
                'personal_info'
            ];

            foreach ($tables as $table) {
                $stmt = $conn->prepare("DELETE FROM $table WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }

            // Finally delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'candidate'");
            $stmt->execute([$user_id]);

            $conn->commit();
            $success = true;
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Failed to delete candidate: " . $e->getMessage();
        }
    }
}

// Fetch all candidates with their details
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            p.first_name,
            p.last_name,
            p.phone_number,
            u.email,
            e.qualification,
            e.institute,
            e.percentage_cgpa,
            exp.years_of_experience,
            exp.previous_job_title,
            exp.company_name,
            s.skills,
            d.cv_path,
            d.certificates_path,
            LOWER(sl.status) as status
        FROM users u
        LEFT JOIN personal_info p ON u.id = p.user_id
        LEFT JOIN education e ON u.id = e.user_id
        LEFT JOIN experience exp ON u.id = exp.user_id
        LEFT JOIN skills s ON u.id = s.user_id
        LEFT JOIN documents d ON u.id = d.user_id
        LEFT JOIN shortlisting sl ON u.id = sl.user_id
        WHERE u.role = 'candidate'
        ORDER BY u.id DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch candidates: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - CV Shortlisting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                        <a class="nav-link active" href="manager_dash.php">All Candidates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shortlisted.php">Shortlisted</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rejected.php">Rejected</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">Job Advertisements</a>
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
                    <div class="card-header">
                        <h5 class="card-title mb-0">Candidates List</h5>
                        <h5 style=" margin-left: 83%;"><a href="shortlisted.php" style="text-decoration: none;">Shortlisted Candidates </a> <a href="jobs.php" style="text-decoration: none; margin-right: 10px">Job Advertisements</a> </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Education</th>
                                        <th>Experience</th>
                                        <th>Skills</th>
                                        <th>Documents</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                            <br>
                                            <small><?php echo htmlspecialchars($user['phone_number']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['qualification']); ?><br>
                                            <?php echo htmlspecialchars($user['institute']); ?><br>
                                            CGPA: <?php echo htmlspecialchars($user['percentage_cgpa']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['years_of_experience']); ?> years<br>
                                            <?php echo htmlspecialchars($user['previous_job_title']); ?><br>
                                            <small><?php echo htmlspecialchars($user['company_name']); ?></small>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#skillsModal<?php echo $user['id']; ?>">
                                                View Skills
                                            </button>
                                            <!-- Modal for skills -->
                                            <div class="modal fade" id="skillsModal<?php echo $user['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Skills</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <?php echo nl2br(htmlspecialchars($user['skills'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($user['cv_path']): ?>
                                                <a href="../<?php echo htmlspecialchars($user['cv_path']); ?>" class="btn btn-primary btn-sm" target="_blank">CV</a>
                                            <?php endif; ?>
                                            <?php if ($user['certificates_path']): ?>
                                                <a href="../<?php echo htmlspecialchars($user['certificates_path']); ?>" class="btn btn-primary btn-sm" target="_blank">Certificates</a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $user['status'] ?? 'Pending';
                                            $badge = 'warning';
                                            if (strtolower(trim($status)) === 'rejected') $badge = 'danger';
                                            else if (strtolower(trim($status)) === 'shortlisted') $badge = 'success';
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this candidate?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_candidate" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>