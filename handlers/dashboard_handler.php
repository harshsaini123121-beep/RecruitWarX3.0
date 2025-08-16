<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$database = new Database();
$db = $database->connect();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_recent_applications':
        if ($_SESSION['role'] === 'candidate') {
            $query = "SELECT a.*, j.title as job_title, c.name as company_name 
                      FROM applications a 
                      JOIN jobs j ON a.job_id = j.id 
                      LEFT JOIN companies c ON j.company_id = c.id 
                      WHERE a.candidate_id = :user_id 
                      ORDER BY a.applied_at DESC LIMIT 10";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
        } else {
            $query = "SELECT a.*, j.title as job_title, u.first_name, u.last_name, u.email 
                      FROM applications a 
                      JOIN jobs j ON a.job_id = j.id 
                      JOIN users u ON a.candidate_id = u.id 
                      WHERE j.posted_by = :user_id 
                      ORDER BY a.applied_at DESC LIMIT 10";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        
        $stmt->execute();
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format dates and status
        foreach ($applications as &$app) {
            $app['applied_date'] = date('M j, Y', strtotime($app['applied_at']));
            $app['status_badge'] = $this->getStatusBadge($app['status']);
        }
        
        echo json_encode(['success' => true, 'applications' => $applications]);
        break;
        
    case 'get_upcoming_interviews':
        $query = "SELECT i.*, a.job_id, j.title as job_title, c.name as company_name,
                         u.first_name, u.last_name 
                  FROM interviews i 
                  JOIN applications a ON i.application_id = a.id 
                  JOIN jobs j ON a.job_id = j.id 
                  LEFT JOIN companies c ON j.company_id = c.id 
                  JOIN users u ON a.candidate_id = u.id 
                  WHERE i.status = 'scheduled' 
                  AND i.scheduled_at > NOW() 
                  AND (a.candidate_id = :user_id OR j.posted_by = :user_id)
                  ORDER BY i.scheduled_at ASC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($interviews as &$interview) {
            $interview['formatted_date'] = date('M j, Y g:i A', strtotime($interview['scheduled_at']));
            $interview['days_until'] = floor((strtotime($interview['scheduled_at']) - time()) / (60 * 60 * 24));
        }
        
        echo json_encode(['success' => true, 'interviews' => $interviews]);
        break;
        
    case 'get_recommended_jobs':
        if ($_SESSION['role'] !== 'candidate') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        // Simple recommendation based on user's previous applications
        $query = "SELECT DISTINCT j.*, c.name as company_name 
                  FROM jobs j 
                  LEFT JOIN companies c ON j.company_id = c.id 
                  WHERE j.status = 'active' 
                  AND j.id NOT IN (
                      SELECT job_id FROM applications WHERE candidate_id = :user_id
                  )
                  ORDER BY j.created_at DESC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jobs as &$job) {
            $job['salary_range'] = '';
            if ($job['salary_min'] && $job['salary_max']) {
                $job['salary_range'] = '$' . number_format($job['salary_min']/1000) . 'k-$' . number_format($job['salary_max']/1000) . 'k';
            }
        }
        
        echo json_encode(['success' => true, 'jobs' => $jobs]);
        break;
        
    case 'get_activity_feed':
        $activities = [];
        
        if ($_SESSION['role'] === 'admin') {
            // Get recent system activities
            $query = "SELECT 'application' as type, a.applied_at as created_at, 
                             CONCAT(u.first_name, ' ', u.last_name) as user_name,
                             j.title as job_title, 'applied for' as action
                      FROM applications a 
                      JOIN users u ON a.candidate_id = u.id 
                      JOIN jobs j ON a.job_id = j.id 
                      ORDER BY a.applied_at DESC LIMIT 10";
        } else if ($_SESSION['role'] === 'recruiter') {
            // Get activities for recruiter's jobs
            $query = "SELECT 'application' as type, a.applied_at as created_at, 
                             CONCAT(u.first_name, ' ', u.last_name) as user_name,
                             j.title as job_title, 'applied for' as action
                      FROM applications a 
                      JOIN users u ON a.candidate_id = u.id 
                      JOIN jobs j ON a.job_id = j.id 
                      WHERE j.posted_by = :user_id
                      ORDER BY a.applied_at DESC LIMIT 10";
        } else {
            // Get candidate's activities
            $query = "SELECT 'application' as type, a.applied_at as created_at, 
                             'You' as user_name, j.title as job_title, 'applied for' as action
                      FROM applications a 
                      JOIN jobs j ON a.job_id = j.id 
                      WHERE a.candidate_id = :user_id
                      ORDER BY a.applied_at DESC LIMIT 10";
        }
        
        $stmt = $db->prepare($query);
        if ($_SESSION['role'] !== 'admin') {
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        $stmt->execute();
        
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($activities as &$activity) {
            $activity['time_ago'] = $this->timeAgo($activity['created_at']);
        }
        
        echo json_encode(['success' => true, 'activities' => $activities]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getStatusBadge($status) {
    $badges = [
        'applied' => 'status-active',
        'screening' => 'status-pending',
        'interview' => 'status-pending',
        'offer' => 'status-active',
        'hired' => 'status-active',
        'rejected' => 'status-rejected'
    ];
    
    return $badges[$status] ?? 'status-draft';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>