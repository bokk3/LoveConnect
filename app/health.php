<?php
// Health check endpoint for Docker health monitoring
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'services' => []
];

// Check database connection
try {
    $db_host = $_ENV['DB_HOST'] ?? 'localhost';
    $db_name = $_ENV['DB_NAME'] ?? 'login_system';
    $db_user = $_ENV['DB_USER'] ?? 'root';
    $db_pass = $_ENV['DB_PASS'] ?? 'rootpassword';
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $health['services']['database'] = 'healthy';
} catch (Exception $e) {
    $health['services']['database'] = 'unhealthy';
    $health['status'] = 'unhealthy';
}

// Check if required files exist
$required_files = ['db.php', 'functions.php'];
foreach ($required_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $health['services'][$file] = 'healthy';
    } else {
        $health['services'][$file] = 'missing';
        $health['status'] = 'unhealthy';
    }
}

// Check write permissions
if (is_writable(__DIR__)) {
    $health['services']['filesystem'] = 'healthy';
} else {
    $health['services']['filesystem'] = 'read-only';
    $health['status'] = 'degraded';
}

http_response_code($health['status'] === 'healthy' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);
?>