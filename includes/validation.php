<?php
/**
 * Validation functions for user data
 */

function validatePersonalInfo($data) {
    $errors = [];
    
    // First Name validation
    if (empty($data['first_name'])) {
        $errors[] = "First name is required";
    } elseif (strlen($data['first_name']) < 2 || strlen($data['first_name']) > 50) {
        $errors[] = "First name must be between 2 and 50 characters";
    } elseif (!preg_match('/^[a-zA-Z\s\-]+$/', $data['first_name'])) {
        $errors[] = "First name can only contain letters, spaces, and hyphens";
    }

    // Last Name validation
    if (empty($data['last_name'])) {
        $errors[] = "Last name is required";
    } elseif (strlen($data['last_name']) < 2 || strlen($data['last_name']) > 50) {
        $errors[] = "Last name must be between 2 and 50 characters";
    } elseif (!preg_match('/^[a-zA-Z\s\-]+$/', $data['last_name'])) {
        $errors[] = "Last name can only contain letters, spaces, and hyphens";
    }

    // Phone Number validation
    if (empty($data['phone_number'])) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9+\-\s()]{10,15}$/', $data['phone_number'])) {
        $errors[] = "Invalid phone number format";
    }

    // Email validation
    if (empty($data['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (strlen($data['email']) > 255) {
        $errors[] = "Email address is too long";
    }

    // Date of Birth validation
    if (empty($data['date_of_birth'])) {
        $errors[] = "Date of birth is required";
    } else {
        $dob = strtotime($data['date_of_birth']);
        $min_age = strtotime('-20 years');
        $max_age = strtotime('-100 years');
        if ($dob > time()) {
            $errors[] = "Date of birth cannot be in the future";
        } elseif ($dob > $min_age) {
            $errors[] = "You must be at least 20 years old";
        } elseif ($dob < $max_age) {
            $errors[] = "Please enter a valid date of birth";
        }
    }

    // Address validation
    if (empty($data['address'])) {
        $errors[] = "Address is required";
    } elseif (strlen($data['address']) < 5 || strlen($data['address']) > 255) {
        $errors[] = "Address must be between 5 and 255 characters";
    }

    // Gender validation
    if (empty($data['gender'])) {
        $errors[] = "Gender is required";
    } elseif (!in_array($data['gender'], ['male', 'female', 'other'])) {
        $errors[] = "Invalid gender selection";
    }

    // Nationality validation
    if (empty($data['nationality'])) {
        $errors[] = "Nationality is required";
    } elseif (strlen($data['nationality']) < 2 || strlen($data['nationality']) > 50) {
        $errors[] = "Nationality must be between 2 and 50 characters";
    }

    return $errors;
}

function validateEducation($data) {
    $errors = [];
    
    // Institution validation
    if (empty($data['institution'])) {
        $errors[] = "Institution name is required";
    } elseif (strlen($data['institution']) < 2 || strlen($data['institution']) > 100) {
        $errors[] = "Institution name must be between 2 and 100 characters";
    }

    // Degree validation
    if (empty($data['degree'])) {
        $errors[] = "Degree is required";
    } elseif (strlen($data['degree']) < 2 || strlen($data['degree']) > 100) {
        $errors[] = "Degree must be between 2 and 100 characters";
    }

    // Field of Study validation
    if (empty($data['field_of_study'])) {
        $errors[] = "Field of study is required";
    } elseif (strlen($data['field_of_study']) < 2 || strlen($data['field_of_study']) > 100) {
        $errors[] = "Field of study must be between 2 and 100 characters";
    }

    // Start Date validation
    if (empty($data['start_date'])) {
        $errors[] = "Start date is required";
    } elseif (!validateDate($data['start_date'])) {
        $errors[] = "Invalid start date format";
    }

    // End Date validation (if not current)
    if (!empty($data['end_date']) && !validateDate($data['end_date'])) {
        $errors[] = "Invalid end date format";
    }

    // Grade validation (if provided)
    if (!empty($data['grade']) && !is_numeric($data['grade'])) {
        $errors[] = "Grade must be a number";
    }

    return $errors;
}

function validateExperience($data) {
    $errors = [];
    
    // Company validation
    if (empty($data['company'])) {
        $errors[] = "Company name is required";
    } elseif (strlen($data['company']) < 2 || strlen($data['company']) > 100) {
        $errors[] = "Company name must be between 2 and 100 characters";
    }

    // Position validation
    if (empty($data['position'])) {
        $errors[] = "Position is required";
    } elseif (strlen($data['position']) < 2 || strlen($data['position']) > 100) {
        $errors[] = "Position must be between 2 and 100 characters";
    }

    // Start Date validation
    if (empty($data['start_date'])) {
        $errors[] = "Start date is required";
    } elseif (!validateDate($data['start_date'])) {
        $errors[] = "Invalid start date format";
    }

    // End Date validation (if not current)
    if (!empty($data['end_date']) && !validateDate($data['end_date'])) {
        $errors[] = "Invalid end date format";
    }

    // Description validation
    if (!empty($data['description']) && strlen($data['description']) > 1000) {
        $errors[] = "Description must not exceed 1000 characters";
    }

    return $errors;
}

function validateSkills($data) {
    $errors = [];
    
    // Skills validation
    if (empty($data['skills'])) {
        $errors[] = "At least one skill is required";
    } elseif (!is_array($data['skills'])) {
        $errors[] = "Invalid skills format";
    } else {
        foreach ($data['skills'] as $skill) {
            if (strlen($skill) < 2 || strlen($skill) > 50) {
                $errors[] = "Each skill must be between 2 and 50 characters";
                break;
            }
        }
    }

    // Proficiency level validation (if provided)
    if (!empty($data['proficiency']) && !in_array($data['proficiency'], ['beginner', 'intermediate', 'advanced', 'expert'])) {
        $errors[] = "Invalid proficiency level";
    }

    return $errors;
}

// Helper function to validate date format
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Helper function to sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
} 