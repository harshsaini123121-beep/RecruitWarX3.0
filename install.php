<?php
// Installation script for RecruitWarX Portal
// Run this file once to set up the database and initial data

require_once 'config/database.php';

echo "<h1>RecruitWarX Portal Installation</h1>";

try {
    // Initialize database
    initializeDatabase();
    echo "<p style='color: green;'>✓ Database and tables created successfully!</p>";
    echo "<p style='color: green;'>✓ Sample data inserted successfully!</p>";
    
    echo "<h2>Installation Complete!</h2>";
    echo "<p>Your RecruitWarX Portal is now ready to use.</p>";
    
    echo "<h3>Demo Login Credentials:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: admin, password: admin123</li>";
    echo "<li><strong>Recruiter:</strong> username: recruiter, password: recruiter123</li>";
    echo "<li><strong>Candidate:</strong> username: candidate, password: candidate123</li>";
    echo "</ul>";
    
    echo "<p><a href='index.html' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Portal</a></p>";
    
    echo "<p style='color: orange; margin-top: 30px;'><strong>Security Note:</strong> Please delete this install.php file after installation for security reasons.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Installation failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
}
?>