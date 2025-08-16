<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$database = new Database();
$db = $database->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_job':
            if (!$auth->isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'recruiter'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'requirements' => $_POST['requirements'] ?? '',
                'location' => $_POST['location'] ?? '',
                'salary_min' => $_POST['salary_min'] ?? null,
                'salary_max' => $_POST['salary_max'] ?? null,
                'job_type' => $_POST['job_type'] ?? '',
                'experience_level' => $_POST['experience_level'] ?? '',
                'company_id' => $_POST['company_id'] ?? 1,
                'status' => $_POST['status'] ?? 'draft'
            ];
            
            // Validate required fields
            $required = ['title', 'description', 'requirements', 'location', 'job_type', 'experience_level'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
                    exit;
                }
            }
            
            $query = "INSERT INTO jobs (title, description, requirements, location, salary_min, salary_max, job_type, experience_level, company_id, posted_by, status) 
                      VALUES (:title, :description, :requirements, :location, :salary_min, :salary_max, :job_type, :experience_level, :company_id, :posted_by, :status)";
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':requirements', $data['requirements']);
            $stmt->bindParam(':location', $data['location']);
            $stmt->bindParam(':salary_min', $data['salary_min']);
            $stmt->bindParam(':salary_max', $data['salary_max']);
            $stmt->bindParam(':job_type', $data['job_type']);
            $stmt->bindParam(':experience_level', $data['experience_level']);
            $stmt->bindParam(':company_id', $data['company_id']);
            $stmt->bindParam(':posted_by', $_SESSION['user_id']);
            $stmt->bindParam(':status', $data['status']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Job created successfully', 'job_id' => $db->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create job']);
            }
            break;
            
        case 'apply_job':
            if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'candidate') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            $job_id = $_POST['job_id'] ?? '';
            $cover_letter = $_POST['cover_letter'] ?? '';
            
            if (empty($job_id)) {
                echo json_encode(['success' => false, 'message' => 'Job ID is required']);
                exit;
            }
            
            // Check if already applied
            $checkQuery = "SELECT id FROM applications WHERE job_id = :job_id AND candidate_id = :candidate_id";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':job_id', $job_id);
            $checkStmt->bindParam(':candidate_id', $_SESSION['user_id']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'You have already applied for this job']);
                exit;
            }
            
            $query = "INSERT INTO applications (job_id, candidate_id, cover_letter) VALUES (:job_id, :candidate_id, :cover_letter)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':job_id', $job_id);
            $stmt->bindParam(':candidate_id', $_SESSION['user_id']);
            $stmt->bindParam(':cover_letter', $cover_letter);
            
            if ($stmt->execute()) {
                // Update application count
                $updateQuery = "UPDATE jobs SET application_count = application_count + 1 WHERE id = :job_id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':job_id', $job_id);
                $updateStmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Application submitted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to submit application']);
            }
            break;
            
        case 'update_application_status':
            if (!$auth->isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'recruiter'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            $application_id = $_POST['application_id'] ?? '';
            $status = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (empty($application_id) || empty($status)) {
                echo json_encode(['success' => false, 'message' => 'Application ID and status are required']);
                exit;
            }
            
            $query = "UPDATE applications SET status = :status, recruiter_notes = :notes WHERE id = :application_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':application_id', $application_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Application status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update application status']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_jobs':
            $search = $_GET['search'] ?? '';
            $job_type = $_GET['job_type'] ?? '';
            $experience_level = $_GET['experience_level'] ?? '';
            $location = $_GET['location'] ?? '';
            $limit = $_GET['limit'] ?? 20;
            $offset = $_GET['offset'] ?? 0;
            
            $query = "SELECT j.*, c.name as company_name FROM jobs j 
                      LEFT JOIN companies c ON j.company_id = c.id 
                      WHERE j.status = 'active'";
            $params = [];
            
            if (!empty($search)) {
                $query .= " AND (j.title LIKE :search OR j.description LIKE :search OR c.name LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            
            if (!empty($job_type)) {
                $query .= " AND j.job_type = :job_type";
                $params[':job_type'] = $job_type;
            }
            
            if (!empty($experience_level)) {
                $query .= " AND j.experience_level = :experience_level";
                $params[':experience_level'] = $experience_level;
            }
            
            if (!empty($location)) {
                $query .= " AND j.location LIKE :location";
                $params[':location'] = '%' . $location . '%';
            }
            
            $query .= " ORDER BY j.created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'jobs' => $jobs]);
            break;
            
        case 'get_applications':
            if (!$auth->isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            if ($_SESSION['role'] === 'candidate') {
                $query = "SELECT a.*, j.title as job_title, c.name as company_name 
                          FROM applications a 
                          JOIN jobs j ON a.job_id = j.id 
                          LEFT JOIN companies c ON j.company_id = c.id 
                          WHERE a.candidate_id = :user_id 
                          ORDER BY a.applied_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
            } else {
                $query = "SELECT a.*, j.title as job_title, u.first_name, u.last_name, u.email 
                          FROM applications a 
                          JOIN jobs j ON a.job_id = j.id 
                          JOIN users u ON a.candidate_id = u.id 
                          WHERE j.posted_by = :user_id 
                          ORDER BY a.applied_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
            }
            
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'applications' => $applications]);
            break;
            
        case 'get_dashboard_stats':
            if (!$auth->isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            
            $stats = [];
            
            if ($_SESSION['role'] === 'admin') {
                // Admin stats
                $stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
                $stats['total_users'] = $stmt->fetchColumn();
                
                $stmt = $db->query("SELECT COUNT(*) as active_jobs FROM jobs WHERE status = 'active'");
                $stats['active_jobs'] = $stmt->fetchColumn();
                
                $stmt = $db->query("SELECT COUNT(*) as total_applications FROM applications");
                $stats['total_applications'] = $stmt->fetchColumn();
                
                $stmt = $db->query("SELECT COUNT(*) as hires_this_month FROM applications WHERE status = 'hired' AND MONTH(updated_at) = MONTH(CURRENT_DATE())");
                $stats['hires_this_month'] = $stmt->fetchColumn();
                
            } else if ($_SESSION['role'] === 'recruiter') {
                // Recruiter stats
                $stmt = $db->prepare("SELECT COUNT(*) as active_jobs FROM jobs WHERE posted_by = :user_id AND status = 'active'");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['active_jobs'] = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) as total_applications FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.posted_by = :user_id");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['total_applications'] = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) as interviews_scheduled FROM interviews i JOIN applications a ON i.application_id = a.id JOIN jobs j ON a.job_id = j.id WHERE j.posted_by = :user_id AND i.status = 'scheduled'");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['interviews_scheduled'] = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) as offers_extended FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.posted_by = :user_id AND a.status = 'offer'");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['offers_extended'] = $stmt->fetchColumn();
                
            } else if ($_SESSION['role'] === 'candidate') {
                // Candidate stats
                $stmt = $db->prepare("SELECT COUNT(*) as applications_sent FROM applications WHERE candidate_id = :user_id");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['applications_sent'] = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) as interviews_scheduled FROM interviews i JOIN applications a ON i.application_id = a.id WHERE a.candidate_id = :user_id AND i.status = 'scheduled'");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['interviews_scheduled'] = $stmt->fetchColumn();
                
                $stats['profile_views'] = rand(100, 200); // Mock data
                $stats['profile_complete'] = 89; // Mock data
            }
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>