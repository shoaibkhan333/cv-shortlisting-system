-- CV Shortlisting System Database Schema
-- Database: cv_shortlisting1

CREATE DATABASE IF NOT EXISTS cv_shortlisting1;
USE cv_shortlisting1;

-- Users table (candidates and managers)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('candidate', 'manager') NOT NULL DEFAULT 'candidate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Personal information
CREATE TABLE IF NOT EXISTS personal_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(255),
    date_of_birth DATE NOT NULL,
    address TEXT NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Education
CREATE TABLE IF NOT EXISTS education (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qualification VARCHAR(100) NOT NULL,
    institute VARCHAR(100) NOT NULL,
    year_of_completion INT NOT NULL,
    percentage_cgpa DECIMAL(5,2) NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Work experience
CREATE TABLE IF NOT EXISTS experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    years_of_experience INT NOT NULL DEFAULT 0,
    previous_job_title VARCHAR(100) NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    responsibilities TEXT NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Skills
CREATE TABLE IF NOT EXISTS skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skills TEXT NOT NULL,
    certificate_path VARCHAR(255),
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Documents (CV and certificates)
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cv_path VARCHAR(255),
    certificates_path VARCHAR(255),
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Shortlisting status
CREATE TABLE IF NOT EXISTS shortlisting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('shortlisted', 'rejected', 'pending') DEFAULT 'pending',
    comments TEXT,
    manager_id INT,
    user_id INT NOT NULL,
    shortlisted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Job advertisements
CREATE TABLE IF NOT EXISTS job_advertisements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    job_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    salary_range VARCHAR(100),
    expiry_date DATE NOT NULL,
    manager_id INT NOT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Job requirements for automated shortlisting
CREATE TABLE IF NOT EXISTS job_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    min_experience INT DEFAULT 0,
    required_qualification VARCHAR(255),
    required_skills TEXT,
    min_cgpa DECIMAL(4,2) DEFAULT 0,
    FOREIGN KEY (job_id) REFERENCES job_advertisements(id) ON DELETE CASCADE
);

-- Job applications
CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    status ENUM('pending', 'shortlisted', 'rejected') DEFAULT 'pending',
    shortlist_reason TEXT,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_advertisements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (user_id, job_id)
);

-- Sample manager account (password: Manager@123)
INSERT INTO users (email, password, role) VALUES
('manager@gmail.com', '$2y$10$CQcPyVvGEzD3e77RdKFqT.ISpzUUXhqkBi//GTs1wmzTiG5dDiln2', 'manager')
ON DUPLICATE KEY UPDATE email = email;
