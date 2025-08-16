<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// All good, create the Auth object
$auth = new Auth();

// Get the action from the form submission, default to empty string if not set
$action = $_POST['action'] ?? '';

// Handle different actions
if ($action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $result = $auth->login($username, $password);
    echo json_encode($result);

} elseif ($action === 'register') {
    // Collect all registration data from the form
    $data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name'  => $_POST['last_name'] ?? '',
        'email'      => $_POST['email'] ?? '',
        'username'   => $_POST['username'] ?? '',
        'phone'      => $_POST['phone'] ?? '',
        'role'       => $_POST['role'] ?? '',
        'password'   => $_POST['password'] ?? ''
    ];
    $result = $auth->register($data);
    echo json_encode($result);

} else {
    // If the action is not 'login' or 'register', send an error
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
}

?>