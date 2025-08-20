<?php
/**
 * Docker Health Check Script
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

// Simple health check for Docker container
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

try {
    // Check if database connection works
    $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'db') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'samfedbiz');
    $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'samfedbiz_user', $_ENV['DB_PASS'] ?? 'samfedbiz_pass_2025');
    $pdo->query('SELECT 1');
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['checks']['database'] = 'fail';
    $health['status'] = 'unhealthy';
}

// Check if required directories are writable
$dirs = ['/var/log/samfedbiz', '/var/www/html/public'];
foreach ($dirs as $dir) {
    if (is_writable($dir)) {
        $health['checks']['writable_' . basename($dir)] = 'ok';
    } else {
        $health['checks']['writable_' . basename($dir)] = 'fail';
        $health['status'] = 'unhealthy';
    }
}

// Check PHP version
$health['php_version'] = PHP_VERSION;
$health['checks']['php'] = 'ok';

// Return appropriate HTTP status
if ($health['status'] === 'healthy') {
    http_response_code(200);
} else {
    http_response_code(503);
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>