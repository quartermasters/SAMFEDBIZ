<?php
###############################################################################
# Health Check Endpoint
# samfedbiz.com - Federal BD Platform
# Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM
###############################################################################

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'environment' => $_ENV['APP_ENV'] ?? 'production',
    'timezone' => date_default_timezone_get(),
    'checks' => []
];

// PHP version check
$health['checks']['php'] = [
    'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'pass' : 'fail',
    'version' => PHP_VERSION
];

// File system checks
$health['checks']['filesystem'] = [
    'status' => 'pass',
    'writable_dirs' => []
];

$writableDirs = [
    'storage/briefs' => is_writable(__DIR__ . '/../storage/briefs'),
    'storage/uploads' => is_writable(__DIR__ . '/../storage/uploads'),
    'reports' => is_writable(__DIR__ . '/../reports'),
    'logs' => is_writable('/var/log/samfedbiz')
];

foreach ($writableDirs as $dir => $writable) {
    $health['checks']['filesystem']['writable_dirs'][$dir] = $writable ? 'pass' : 'fail';
    if (!$writable) {
        $health['checks']['filesystem']['status'] = 'fail';
    }
}

// Database check (if configured)
if (!empty($_ENV['DB_HOST'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $health['checks']['database'] = [
            'status' => 'pass',
            'connection' => 'ok'
        ];
    } catch (Exception $e) {
        $health['checks']['database'] = [
            'status' => 'fail',
            'error' => 'Connection failed'
        ];
    }
} else {
    $health['checks']['database'] = [
        'status' => 'skip',
        'reason' => 'not_configured'
    ];
}

// Cron check
$cronStatus = shell_exec('service cron status 2>/dev/null');
$health['checks']['cron'] = [
    'status' => (strpos($cronStatus, 'active') !== false) ? 'pass' : 'fail',
    'service' => $cronStatus ? 'running' : 'stopped'
];

// Essential files check
$essentialFiles = [
    'src/Core/Database.php',
    'src/Core/EnvManager.php',
    'src/Adapters/TLSAdapter.php',
    'src/Adapters/OASISPlusAdapter.php',
    'src/Adapters/SEWPAdapter.php',
    'scripts/quality-gates.sh',
    'cron/news_scan.php',
    'cron/brief_build.php',
    'cron/brief_send.php'
];

$health['checks']['files'] = ['status' => 'pass', 'missing' => []];
foreach ($essentialFiles as $file) {
    if (!file_exists(__DIR__ . '/../' . $file)) {
        $health['checks']['files']['missing'][] = $file;
        $health['checks']['files']['status'] = 'fail';
    }
}

// Extensions check
$requiredExtensions = ['pdo_mysql', 'mbstring', 'gd', 'zip', 'bcmath'];
$health['checks']['extensions'] = ['status' => 'pass', 'missing' => []];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $health['checks']['extensions']['missing'][] = $ext;
        $health['checks']['extensions']['status'] = 'fail';
    }
}

// Quality gates status
$qualityReport = __DIR__ . '/../reports/quality-report.txt';
if (file_exists($qualityReport)) {
    $reportContent = file_get_contents($qualityReport);
    $totalErrors = 0;
    if (preg_match('/TOTAL ERRORS: (\d+)/', $reportContent, $matches)) {
        $totalErrors = (int)$matches[1];
    }
    $health['checks']['quality_gates'] = [
        'status' => $totalErrors === 0 ? 'pass' : 'warn',
        'total_errors' => $totalErrors,
        'last_run' => date('c', filemtime($qualityReport))
    ];
} else {
    $health['checks']['quality_gates'] = [
        'status' => 'skip',
        'reason' => 'not_run'
    ];
}

// Overall health determination
$failed = array_filter($health['checks'], function($check) {
    return $check['status'] === 'fail';
});

if (!empty($failed)) {
    $health['status'] = 'unhealthy';
    http_response_code(503);
} else {
    $warnings = array_filter($health['checks'], function($check) {
        return $check['status'] === 'warn';
    });
    if (!empty($warnings)) {
        $health['status'] = 'degraded';
    }
}

// Add performance metrics
$health['performance'] = [
    'memory_usage' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true),
    'uptime' => $_SERVER['REQUEST_TIME'] - @filemtime('/proc/1/stat') ?: 0
];

// Add platform info
$health['platform'] = [
    'php_version' => PHP_VERSION,
    'os' => PHP_OS,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'timezone' => date_default_timezone_get()
];

echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>