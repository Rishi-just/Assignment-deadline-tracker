CREATE DATABASE assignment_beta;
USE assignment_beta;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE  assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    deadline DATETIME NOT NULL,
    pdf_file VARCHAR(255) DEFAULT NULL,
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    assignment_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    submission_pdf VARCHAR(255) DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    UNIQUE KEY one_status_per_student (student_id, assignment_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
);
