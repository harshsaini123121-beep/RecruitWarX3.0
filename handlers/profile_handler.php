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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $data = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'location' => $_POST['location'] ?? '',
                'bio' => $_POST['bio'] ?? ''
            ];
            
            $query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                      email = :email, phone = :phone, location = :location, bio = :bio 
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
            }
            break;
            
        case 'save_resume':
            $resume_data = [
                'work_experience' => $_POST['work_experience'] ?? '',
                'skills' => $_POST['skills'] ?? '',
                'education' => $_POST['education'] ?? '',
                'projects' => $_POST['projects'] ?? '',
                'certifications' => $_POST['certifications'] ?? '',
                'summary' => $_POST['summary'] ?? ''
            ];
            
            $query = "UPDATE users SET 
                      experience_years = :experience_years,
                      skills = :skills,
                      bio = :summary
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            
            $stmt->bindValue(':experience_years', $_POST['experience_years'] ?? 0);
            $stmt->bindValue(':skills', $resume_data['skills']);
            $stmt->bindValue(':summary', $resume_data['summary']);
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $resume_query = "INSERT INTO user_resume (user_id, work_experience, education, projects, certifications, updated_at) 
                                VALUES (:user_id, :work_experience, :education, :projects, :certifications, NOW())
                                ON DUPLICATE KEY UPDATE 
                                work_experience = VALUES(work_experience),
                                education = VALUES(education),
                                projects = VALUES(projects),
                                certifications = VALUES(certifications),
                                updated_at = NOW()";
                
                $resume_stmt = $db->prepare($resume_query);
                $resume_stmt->bindValue(':user_id', $_SESSION['user_id']);
                $resume_stmt->bindValue(':work_experience', $resume_data['work_experience']);
                $resume_stmt->bindValue(':education', $resume_data['education']);
                $resume_stmt->bindValue(':projects', $resume_data['projects']);
                $resume_stmt->bindValue(':certifications', $resume_data['certifications']);
                
                if ($resume_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Resume saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save resume data']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_profile':
            $query = "SELECT u.*, ur.work_experience, ur.education, ur.projects, ur.certifications 
                      FROM users u 
                      LEFT JOIN user_resume ur ON u.id = ur.user_id 
                      WHERE u.id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($profile) {
                $profile['profile_completion'] = calculateProfileCompletion($profile);
                echo json_encode(['success' => true, 'profile' => $profile]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Profile not found']);
            }
            break;
            
        case 'get_stats':
            $stats = [];
            
            if ($_SESSION['role'] === 'candidate') {
                $stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = :user_id");
                $stmt->bindValue(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['applications_sent'] = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM interviews i 
                                     JOIN applications a ON i.application_id = a.id 
                                     WHERE a.candidate_id = :user_id AND i.status = 'scheduled'");
                $stmt->bindValue(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['interviews_scheduled'] = $stmt->fetchColumn();
                
                $stats['profile_views'] = rand(100, 200);
                
                $query = "SELECT * FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['response_rate'] = rand(20, 40);
            }
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function calculateProfileCompletion($profile) {
    $fields = ['first_name', 'last_name', 'email', 'phone', 'location', 'bio', 'skills'];
    $completed = 0;
    
    foreach ($fields as $field) {
        if (!empty($profile[$field])) {
            $completed++;
        }
    }
    
    if (!empty($profile['work_experience'])) $completed++;
    if (!empty($profile['education'])) $completed++;
    
    return round(($completed / 9) * 100);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>