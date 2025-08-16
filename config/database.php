<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'recruitwarx_portal');

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->dbname, $this->user, $this->pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }
        return $this->conn;
    }
}

// Create database and tables if they don't exist
function initializeDatabase() {
    try {
        // First connect without database to create it
        $conn = new PDO('mysql:host=' . DB_HOST, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $conn->exec("USE " . DB_NAME);
        
        // Create tables
        createTables($conn);
        insertSampleData($conn);
        
    } catch(PDOException $e) {
        echo "Database initialization error: " . $e->getMessage();
    }
}

function createTables($conn) {
    // Users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'recruiter', 'hiring_manager', 'candidate') NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        phone VARCHAR(20),
        profile_image VARCHAR(255),
        bio TEXT,
        skills TEXT,
        experience_years INT DEFAULT 0,
        location VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL
    )";
    $conn->exec($sql);
    
    // Companies table
    $sql = "CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        website VARCHAR(255),
        logo VARCHAR(255),
        industry VARCHAR(100),
        size ENUM('startup', 'small', 'medium', 'large', 'enterprise'),
        location VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // Jobs table
    $sql = "CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        requirements TEXT NOT NULL,
        location VARCHAR(100) NOT NULL,
        salary_min DECIMAL(10,2),
        salary_max DECIMAL(10,2),
        job_type ENUM('full-time', 'part-time', 'contract', 'internship') NOT NULL,
        experience_level ENUM('entry', 'mid', 'senior', 'executive') NOT NULL,
        remote_allowed BOOLEAN DEFAULT FALSE,
        company_id INT,
        posted_by INT NOT NULL,
        status ENUM('draft', 'active', 'closed', 'expired') DEFAULT 'draft',
        expires_at DATE,
        application_count INT DEFAULT 0,
        views_count INT DEFAULT 0,
        featured BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
        FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_job_type (job_type),
        INDEX idx_experience_level (experience_level),
        INDEX idx_location (location)
    )";
    $conn->exec($sql);
    
    // Applications table
    $sql = "CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        candidate_id INT NOT NULL,
        status ENUM('applied', 'screening', 'phone_interview', 'technical_interview', 'final_interview', 'offer', 'rejected', 'hired', 'withdrawn') DEFAULT 'applied',
        cover_letter TEXT,
        resume_path VARCHAR(255),
        portfolio_url VARCHAR(255),
        notes TEXT,
        recruiter_notes TEXT,
        salary_expectation DECIMAL(10,2),
        availability_date DATE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
        FOREIGN KEY (candidate_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_application (job_id, candidate_id),
        INDEX idx_status (status),
        INDEX idx_candidate (candidate_id),
        INDEX idx_job (job_id)
    )";
    $conn->exec($sql);
    
    // Interviews table
    $sql = "CREATE TABLE IF NOT EXISTS interviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        interviewer_id INT NOT NULL,
        interview_type ENUM('phone', 'video', 'in_person', 'technical', 'behavioral') NOT NULL,
        scheduled_at DATETIME NOT NULL,
        duration_minutes INT DEFAULT 60,
        location VARCHAR(255),
        meeting_link VARCHAR(255),
        status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled', 'no_show') DEFAULT 'scheduled',
        feedback TEXT,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
        FOREIGN KEY (interviewer_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        subject VARCHAR(255),
        message TEXT NOT NULL,
        application_id INT,
        is_read BOOLEAN DEFAULT FALSE,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
        INDEX idx_receiver (receiver_id),
        INDEX idx_sender (sender_id)
    )";
    $conn->exec($sql);
    
    // Notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('application', 'interview', 'message', 'job_match', 'system') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        action_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_unread (user_id, is_read)
    )";
    $conn->exec($sql);
}

function insertSampleData($conn) {
    // Check if data already exists
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() > 0) {
        return; // Data already exists
    }
    
    // Insert sample companies
    $sql = "INSERT INTO companies (name, description, website, industry, size, location) VALUES
        ('TechCorp Inc.', 'Leading technology solutions provider', 'https://techcorp.com', 'Technology', 'large', 'San Francisco, CA'),
        ('StartupXYZ', 'Innovative startup focused on mobile apps', 'https://startupxyz.com', 'Technology', 'startup', 'Austin, TX'),
        ('Creative Agency', 'Full-service digital marketing agency', 'https://creativeagency.com', 'Marketing', 'medium', 'New York, NY'),
        ('Innovation Labs', 'Research and development company', 'https://innovationlabs.com', 'Technology', 'medium', 'Boston, MA')";
    $conn->exec($sql);
    
    // Insert sample users with hashed passwords
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $recruiterPass = password_hash('recruiter123', PASSWORD_DEFAULT);
    $candidatePass = password_hash('candidate123', PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES
        ('admin', 'admin@recruitwarx.com', '$adminPass', 'admin', 'Admin', 'User', '+1-555-0001'),
        ('recruiter', 'recruiter@recruitwarx.com', '$recruiterPass', 'recruiter', 'Jane', 'Recruiter', '+1-555-0002'),
        ('candidate', 'candidate@recruitwarx.com', '$candidatePass', 'candidate', 'John', 'Doe', '+1-555-0003')";
    $conn->exec($sql);
    
    // Insert sample jobs
    $sql = "INSERT INTO jobs (title, description, requirements, location, salary_min, salary_max, job_type, experience_level, company_id, posted_by, status) VALUES
        ('Senior Software Engineer', 'We are looking for a Senior Software Engineer to join our growing team. You will work on cutting-edge projects using React, Node.js, and AWS.', 'Bachelor degree in Computer Science, 5+ years experience, React, Node.js, AWS', 'San Francisco, CA', 120000, 150000, 'full-time', 'senior', 1, 2, 'active'),
        ('UX/UI Designer', 'Join our creative team as a UX/UI Designer. You will design user-centered digital experiences for our clients.', 'Portfolio required, Figma, Adobe Creative Suite, 3+ years experience', 'New York, NY', 80000, 100000, 'full-time', 'mid', 3, 2, 'active'),
        ('Data Analyst', 'Looking for a Data Analyst to help analyze business metrics and create insightful reports.', 'SQL, Python, Tableau, 2+ years experience', 'Remote', 70000, 90000, 'contract', 'mid', 4, 2, 'active'),
        ('Junior Frontend Developer', 'Perfect opportunity for a Junior Frontend Developer to join our innovative startup.', 'HTML, CSS, JavaScript, React basics, Fresh graduate or 1 year experience', 'Austin, TX', 60000, 75000, 'full-time', 'entry', 2, 2, 'active')";
    $conn->exec($sql);
    
    // Insert sample applications
    $sql = "INSERT INTO applications (job_id, candidate_id, status, cover_letter) VALUES
        (1, 3, 'applied', 'I am very interested in this position and believe my skills align well with your requirements.'),
        (2, 3, 'screening', 'I have extensive experience in UX/UI design and would love to contribute to your team.'),
        (3, 3, 'interview', 'My background in data analysis makes me a perfect fit for this role.')";
    $conn->exec($sql);
}

// Initialize database on first load
initializeDatabase();
?>