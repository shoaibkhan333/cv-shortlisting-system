<?php
require_once 'db_config.php';

function checkJobRequirements($conn, $user_id, $job_id) {
    try {
        // Get user's profile data
        $stmt = $conn->prepare("
            SELECT 
                e.qualification,
                e.percentage_cgpa,
                exp.years_of_experience,
                s.skills
            FROM users u
            LEFT JOIN education e ON u.id = e.user_id
            LEFT JOIN experience exp ON u.id = exp.user_id
            LEFT JOIN skills s ON u.id = s.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get job requirements
        $stmt = $conn->prepare("
            SELECT 
                min_experience,
                required_qualification,
                required_skills,
                min_cgpa
            FROM job_requirements
            WHERE job_id = ?
        ");
        $stmt->execute([$job_id]);
        $job_requirements = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job_requirements) {
            return [
                'status' => 'error',
                'reason' => 'Job requirements not found'
            ];
        }

        $reasons = [];
        $meets_requirements = true;

        // Check experience
        if ($job_requirements['min_experience'] > 0) {
            $user_experience = $user_data['years_of_experience'] ?? 0;
            if ($user_experience < $job_requirements['min_experience']) {
                $meets_requirements = false;
                $reasons[] = "Insufficient experience (Required: {$job_requirements['min_experience']} years, Your: {$user_experience} years)";
            }
        }

        // Check qualification
        if (!empty($job_requirements['required_qualification'])) {
            $user_qualification = strtolower(trim($user_data['qualification'] ?? ''));
            $required_qualifications = array_map('trim', explode(',', strtolower($job_requirements['required_qualification'])));
            $qualification_match = false;
            foreach ($required_qualifications as $req_qual) {
                if (stripos($user_qualification, $req_qual) !== false) {
                    $qualification_match = true;
                    break;
                }
            }
            if (!$qualification_match) {
                $meets_requirements = false;
                $reasons[] = "Required qualification not met (Required: {$job_requirements['required_qualification']})";
            }
        }

        // Check CGPA
        if ($job_requirements['min_cgpa'] > 0) {
            $user_cgpa = floatval($user_data['percentage_cgpa'] ?? 0);
            if ($user_cgpa < $job_requirements['min_cgpa']) {
                $meets_requirements = false;
                $reasons[] = "CGPA requirement not met (Required: {$job_requirements['min_cgpa']}, Your: {$user_cgpa})";
            }
        }

        // Check required skills
        if (!empty($job_requirements['required_skills'])) {
            $required_skills = array_map('trim', explode(',', strtolower($job_requirements['required_skills'])));
            $user_skills = array_map('trim', explode(',', strtolower($user_data['skills'] ?? '')));
            $has_any_skill = false;
            foreach ($required_skills as $req_skill) {
                foreach ($user_skills as $user_skill) {
                    if (stripos($user_skill, $req_skill) !== false) {
                        $has_any_skill = true;
                        break 2; // At least one skill matches, that's enough
                    }
                }
            }
            if (!$has_any_skill) {
                $meets_requirements = false;
                $reasons[] = "At least one required skill must match (Required: " . implode(', ', $required_skills) . ")";
            }
        }

        return [
            'status' => $meets_requirements ? 'shortlisted' : 'rejected',
            'reason' => $meets_requirements ? 'Meets all requirements' : implode('; ', $reasons)
        ];

    } catch (PDOException $e) {
        error_log("Error in checkJobRequirements: " . $e->getMessage());
        return [
            'status' => 'error',
            'reason' => 'Database error: ' . $e->getMessage()
        ];
    }
}

function updateApplicationStatus($conn, $userId, $jobId, $status, $reason = '') {
    try {
        // First check if application exists
        $checkStmt = $conn->prepare("SELECT id FROM job_applications WHERE user_id = ? AND job_id = ?");
        $checkStmt->execute([$userId, $jobId]);
        $application = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($application) {
            // Update existing application
            $stmt = $conn->prepare("
                UPDATE job_applications 
                SET status = ?, 
                    shortlist_reason = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ? AND job_id = ?
            ");
            return $stmt->execute([$status, $reason, $userId, $jobId]);
        } else {
            // Insert new application
            $stmt = $conn->prepare("
                INSERT INTO job_applications 
                (user_id, job_id, status, shortlist_reason, application_date) 
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            return $stmt->execute([$userId, $jobId, $status, $reason]);
        }
    } catch (PDOException $e) {
        error_log("Error updating application status: " . $e->getMessage());
        throw new Exception("Failed to update application status: " . $e->getMessage());
    }
}

function processJobApplication($userId, $jobId) {
    try {
        $conn = getDBConnection();
        $conn->beginTransaction();

        // Check if application already exists
        $checkStmt = $conn->prepare("SELECT status FROM job_applications WHERE user_id = ? AND job_id = ?");
        $checkStmt->execute([$userId, $jobId]);
        $existingApplication = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingApplication) {
            throw new Exception("You have already applied for this job.");
        }

        // Check job requirements
        $requirements = checkJobRequirements($conn, $userId, $jobId);
        
        if ($requirements['status'] === 'shortlisted') {
            // Shortlist the candidate
            updateApplicationStatus($conn, $userId, $jobId, 'shortlisted', $requirements['reason']);
            // Also update shortlisting table
            $stmt = $conn->prepare("SELECT id FROM shortlisting WHERE user_id = ?");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("UPDATE shortlisting SET status = 'shortlisted', comments = ? WHERE user_id = ?");
                $stmt->execute([$requirements['reason'], $userId]);
            } else {
                $stmt = $conn->prepare("INSERT INTO shortlisting (user_id, status, comments) VALUES (?, 'shortlisted', ?)");
                $stmt->execute([$userId, $requirements['reason']]);
            }
        } else {
            // Reject the candidate
            updateApplicationStatus($conn, $userId, $jobId, 'rejected', $requirements['reason']);
            // Also update shortlisting table
            $stmt = $conn->prepare("SELECT id FROM shortlisting WHERE user_id = ?");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("UPDATE shortlisting SET status = 'rejected', comments = ? WHERE user_id = ?");
                $stmt->execute([$requirements['reason'], $userId]);
            } else {
                $stmt = $conn->prepare("INSERT INTO shortlisting (user_id, status, comments) VALUES (?, 'rejected', ?)");
                $stmt->execute([$userId, $requirements['reason']]);
            }
        }

        $conn->commit();
        return $requirements;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error processing application: " . $e->getMessage());
        throw new Exception("Error processing application: " . $e->getMessage());
    }
}
?> 