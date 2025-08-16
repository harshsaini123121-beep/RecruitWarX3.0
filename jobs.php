<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$database = new Database();
$db = $database->connect();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = $_GET['search'] ?? '';
    $job_type = $_GET['job_type'] ?? '';
    $experience_level = $_GET['experience_level'] ?? '';
    $location = $_GET['location'] ?? '';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    $query = "SELECT j.*, c.name as company_name, c.logo as company_logo 
              FROM jobs j 
              LEFT JOIN companies c ON j.company_id = c.id 
              WHERE j.status = 'active'";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (j.title LIKE :search OR j.description LIKE :search OR c.name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($job_type)) {
        $types = explode(',', $job_type);
        $placeholders = [];
        foreach ($types as $i => $type) {
            $placeholder = ':job_type_' . $i;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $type;
        }
        $query .= " AND j.job_type IN (" . implode(',', $placeholders) . ")";
    }
    
    if (!empty($experience_level)) {
        $levels = explode(',', $experience_level);
        $placeholders = [];
        foreach ($levels as $i => $level) {
            $placeholder = ':exp_level_' . $i;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $level;
        }
        $query .= " AND j.experience_level IN (" . implode(',', $placeholders) . ")";
    }
    
    if (!empty($location)) {
        $query .= " AND (j.location LIKE :location OR j.remote_allowed = 1)";
        $params[':location'] = '%' . $location . '%';
    }
    
    $query .= " ORDER BY j.featured DESC, j.created_at DESC LIMIT :limit OFFSET :offset";
    
    try {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the jobs data
        foreach ($jobs as &$job) {
            $job['salary_range'] = '';
            if ($job['salary_min'] && $job['salary_max']) {
                $job['salary_range'] = '$' . number_format($job['salary_min']) . ' - $' . number_format($job['salary_max']);
            } elseif ($job['salary_min']) {
                $job['salary_range'] = 'From $' . number_format($job['salary_min']);
            } elseif ($job['salary_max']) {
                $job['salary_range'] = 'Up to $' . number_format($job['salary_max']);
            }
            
            $job['posted_date'] = date('M j, Y', strtotime($job['created_at']));
            $job['days_ago'] = floor((time() - strtotime($job['created_at'])) / (60 * 60 * 24));
        }
        
        echo json_encode([
            'success' => true,
            'jobs' => $jobs,
            'total' => count($jobs)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>