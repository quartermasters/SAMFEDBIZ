<?php
/**
 * Settings/Admin Panel
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /settings/
 * Main admin panel with system configuration
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Core\ProgramRegistry;
use SamFedBiz\Config\EnvManager;

// Initialize managers
$envManager = new EnvManager();
$authManager = new AuthManager($pdo);
$programRegistry = new ProgramRegistry($pdo);

// Check authentication
if (!$authManager->isAuthenticated()) {
    header('Location: /auth/login.php');
    exit;
}

$user = $authManager->getCurrentUser();

// Only admin and ops can access settings
if ($user['role'] === 'viewer') {
    http_response_code(403);
    echo "Access denied. Admin or ops role required.";
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$authManager->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
    } else {
        handleSettingsUpdate($_POST, $pdo, $user['id']);
    }
    
    header('Location: /settings/');
    exit;
}

// Get current settings
$settings = getCurrentSettings($pdo, $envManager, $programRegistry);

// Get CSRF token
$csrf_token = $authManager->generateCSRFToken();

// Page title and meta
$page_title = "Settings & Administration";
$meta_description = "System administration panel for samfedbiz.com federal BD platform configuration.";

function getCurrentSettings($pdo, $envManager, $programRegistry) {
    $settings = [];
    
    // Program toggles
    $settings['programs'] = $programRegistry->getPrograms();
    
    // OAuth status
    $settings['oauth'] = [
        'google_configured' => !empty($envManager->get('GOOGLE_CLIENT_ID')),
        'google_connected' => false
    ];
    
    // Check if Google OAuth is connected
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM oauth_tokens 
        WHERE service = 'google_drive' AND expires_at > NOW()
    ");
    $stmt->execute();
    $settings['oauth']['google_connected'] = $stmt->fetchColumn() > 0;
    
    // SMTP status
    $settings['smtp'] = [
        'configured' => !empty($envManager->get('SMTP_HOST')),
        'host' => $envManager->get('SMTP_HOST') ? maskValue($envManager->get('SMTP_HOST')) : '',
        'user' => $envManager->get('SMTP_USER') ? maskValue($envManager->get('SMTP_USER')) : '',
        'port' => $envManager->get('SMTP_PORT') ?: '587'
    ];
    
    // Subscriber stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified
        FROM subscribers
    ");
    $stmt->execute();
    $settings['subscribers'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Blacklisted holders
    $stmt = $pdo->prepare("
        SELECT holder_name FROM holder_blacklist ORDER BY holder_name
    ");
    $stmt->execute();
    $settings['blacklisted_holders'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // System stats
    $settings['system'] = [
        'php_version' => PHP_VERSION,
        'timezone' => date_default_timezone_get(),
        'environment' => $envManager->get('APP_ENV') ?: 'development'
    ];
    
    return $settings;
}

function maskValue($value) {
    if (strlen($value) <= 4) {
        return str_repeat('*', strlen($value));
    }
    return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
}

function handleSettingsUpdate($data, $pdo, $userId) {
    try {
        // Handle program toggles
        if (isset($data['action']) && $data['action'] === 'toggle_program') {
            $programCode = $data['program_code'] ?? '';
            $enabled = isset($data['enabled']) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE programs SET enabled = ? WHERE code = ?
            ");
            $stmt->execute([$enabled, $programCode]);
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
                VALUES ('system', 'settings', 'program_toggle', ?, ?, NOW())
            ");
            $stmt->execute([
                json_encode([
                    'program_code' => $programCode,
                    'enabled' => $enabled,
                    'message' => ($enabled ? 'Enabled' : 'Disabled') . " program: {$programCode}"
                ]),
                $userId
            ]);
            
            $_SESSION['success'] = "Program {$programCode} " . ($enabled ? 'enabled' : 'disabled') . " successfully.";
        }
        
        // Handle blacklist updates
        if (isset($data['action']) && $data['action'] === 'update_blacklist') {
            $holderNames = array_filter(array_map('trim', explode("\n", $data['blacklisted_holders'] ?? '')));
            
            // Clear existing blacklist
            $pdo->exec("DELETE FROM holder_blacklist");
            
            // Add new entries
            $stmt = $pdo->prepare("
                INSERT INTO holder_blacklist (holder_name, added_by, created_at)
                VALUES (?, ?, NOW())
            ");
            
            foreach ($holderNames as $holderName) {
                if (!empty($holderName)) {
                    $stmt->execute([$holderName, $userId]);
                }
            }
            
            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
                VALUES ('system', 'settings', 'blacklist_update', ?, ?, NOW())
            ");
            $stmt->execute([
                json_encode([
                    'holders_count' => count($holderNames),
                    'message' => 'Updated holder blacklist'
                ]),
                $userId
            ]);
            
            $_SESSION['success'] = "Holder blacklist updated successfully.";
        }
        
    } catch (Exception $e) {
        error_log("Settings update failed: " . $e->getMessage());
        $_SESSION['error'] = "Settings update failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title><?php echo htmlspecialchars($page_title); ?> | samfedbiz.com</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/styles/main.css">
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://unpkg.com/gsap@3.12.2/dist/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.27/dist/lenis.min.js"></script>
</head>
<body class="settings-admin">
    <!-- Skip Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Navigation -->
    <nav class="nav-main" role="navigation" aria-label="Main navigation">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/" class="nav-brand-link" aria-label="samfedbiz.com home">
                    <span class="nav-brand-text">samfedbiz</span>
                </a>
            </div>
            
            <div class="nav-links">
                <a href="/" class="nav-link">Dashboard</a>
                <a href="/programs/tls" class="nav-link">TLS</a>
                <a href="/programs/oasis+" class="nav-link">OASIS+</a>
                <a href="/programs/sewp" class="nav-link">SEWP</a>
                <a href="/briefs" class="nav-link">Briefs</a>
                <a href="/research" class="nav-link">Research</a>
                <a href="/settings" class="nav-link active">Settings</a>
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content" class="settings-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li>Settings</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- Settings icon -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">Settings & Administration</h1>
                            <p class="page-description">System configuration and management panel</p>
                        </div>
                    </div>
                    
                    <div class="page-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $settings['subscribers']['active']; ?></span>
                            <span class="stat-label">Active Subscribers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo count($settings['programs']); ?></span>
                            <span class="stat-label">Programs</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo ucfirst($settings['system']['environment']); ?></span>
                            <span class="stat-label">Environment</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <section class="flash-messages">
            <div class="container">
                <div class="flash-message success">
                    <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <section class="flash-messages">
            <div class="container">
                <div class="flash-message error">
                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Settings Content -->
        <section class="settings-content">
            <div class="container">
                <div class="settings-grid">
                    <!-- Program Configuration -->
                    <div class="settings-section glassmorphism reveal">
                        <div class="settings-section-header">
                            <h2 class="settings-section-title">Program Configuration</h2>
                            <p class="settings-section-description">Enable or disable federal contracting programs</p>
                        </div>
                        
                        <div class="settings-section-content">
                            <?php foreach ($settings['programs'] as $program): ?>
                            <div class="program-toggle-item">
                                <div class="program-toggle-info">
                                    <h3 class="program-toggle-name"><?php echo htmlspecialchars($program['name']); ?></h3>
                                    <p class="program-toggle-description"><?php echo htmlspecialchars($program['description']); ?></p>
                                    <span class="program-toggle-adapter">Adapter: <?php echo htmlspecialchars($program['adapter']); ?></span>
                                </div>
                                
                                <form method="POST" class="program-toggle-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="toggle_program">
                                    <input type="hidden" name="program_code" value="<?php echo htmlspecialchars($program['code']); ?>">
                                    
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               name="enabled" 
                                               <?php echo $program['enabled'] ? 'checked' : ''; ?>
                                               onchange="this.form.submit()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- OAuth Integration Status -->
                    <div class="settings-section glassmorphism reveal">
                        <div class="settings-section-header">
                            <h2 class="settings-section-title">OAuth Integration</h2>
                            <p class="settings-section-description">External service authentication status</p>
                        </div>
                        
                        <div class="settings-section-content">
                            <div class="oauth-status-grid">
                                <!-- Google Drive -->
                                <div class="oauth-service-item">
                                    <div class="oauth-service-info">
                                        <div class="oauth-service-icon">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M6.5 10L12 2L17.5 10H6.5Z" fill="#0066DA"/>
                                                <path d="M2 15L6.5 7L12 15H2Z" fill="#00AC47"/>
                                                <path d="M12 15L17.5 7L22 15H12Z" fill="#EA4335"/>
                                                <path d="M6.5 22L12 14L17.5 22H6.5Z" fill="#FFBA00"/>
                                            </svg>
                                        </div>
                                        <div class="oauth-service-details">
                                            <h3 class="oauth-service-name">Google Drive</h3>
                                            <p class="oauth-service-description">Research document synchronization</p>
                                        </div>
                                    </div>
                                    
                                    <div class="oauth-service-status">
                                        <span class="status-indicator <?php echo $settings['oauth']['google_configured'] ? 'configured' : 'not-configured'; ?>">
                                            <?php echo $settings['oauth']['google_configured'] ? 'Configured' : 'Not Configured'; ?>
                                        </span>
                                        
                                        <?php if ($settings['oauth']['google_configured']): ?>
                                        <span class="connection-indicator <?php echo $settings['oauth']['google_connected'] ? 'connected' : 'disconnected'; ?>">
                                            <?php echo $settings['oauth']['google_connected'] ? 'Connected' : 'Disconnected'; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="oauth-service-actions">
                                        <?php if ($settings['oauth']['google_configured'] && !$settings['oauth']['google_connected']): ?>
                                        <button class="btn-secondary" onclick="window.location.href='/api/oauth/google/auth'">Connect</button>
                                        <?php elseif ($settings['oauth']['google_connected']): ?>
                                        <button class="btn-text" onclick="if(confirm('Disconnect Google Drive?')) window.location.href='/api/oauth/google/disconnect'">Disconnect</button>
                                        <?php else: ?>
                                        <span class="text-muted">Configure in environment variables</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Configuration -->
                    <div class="settings-section glassmorphism reveal">
                        <div class="settings-section-header">
                            <h2 class="settings-section-title">Email Configuration</h2>
                            <p class="settings-section-description">SMTP settings for outbound emails</p>
                        </div>
                        
                        <div class="settings-section-content">
                            <div class="smtp-status-grid">
                                <div class="smtp-config-item">
                                    <label class="smtp-config-label">SMTP Host</label>
                                    <div class="smtp-config-value">
                                        <?php echo $settings['smtp']['configured'] ? $settings['smtp']['host'] : 'Not configured'; ?>
                                    </div>
                                </div>
                                
                                <div class="smtp-config-item">
                                    <label class="smtp-config-label">SMTP User</label>
                                    <div class="smtp-config-value">
                                        <?php echo $settings['smtp']['configured'] ? $settings['smtp']['user'] : 'Not configured'; ?>
                                    </div>
                                </div>
                                
                                <div class="smtp-config-item">
                                    <label class="smtp-config-label">SMTP Port</label>
                                    <div class="smtp-config-value">
                                        <?php echo $settings['smtp']['port']; ?>
                                    </div>
                                </div>
                                
                                <div class="smtp-config-item">
                                    <label class="smtp-config-label">Status</label>
                                    <div class="smtp-config-value">
                                        <span class="status-indicator <?php echo $settings['smtp']['configured'] ? 'configured' : 'not-configured'; ?>">
                                            <?php echo $settings['smtp']['configured'] ? 'Configured' : 'Not Configured'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="smtp-actions">
                                <button class="btn-secondary" id="test-smtp-btn">Test SMTP Connection</button>
                                <small class="text-muted">Configure SMTP settings in environment variables</small>
                            </div>
                        </div>
                    </div>

                    <!-- Subscriber Management -->
                    <div class="settings-section glassmorphism reveal">
                        <div class="settings-section-header">
                            <h2 class="settings-section-title">Subscriber Management</h2>
                            <p class="settings-section-description">Newsletter subscriber statistics and management</p>
                        </div>
                        
                        <div class="settings-section-content">
                            <div class="subscriber-stats-grid">
                                <div class="subscriber-stat-item">
                                    <span class="subscriber-stat-value"><?php echo number_format($settings['subscribers']['total']); ?></span>
                                    <span class="subscriber-stat-label">Total Subscribers</span>
                                </div>
                                
                                <div class="subscriber-stat-item">
                                    <span class="subscriber-stat-value"><?php echo number_format($settings['subscribers']['active']); ?></span>
                                    <span class="subscriber-stat-label">Active</span>
                                </div>
                                
                                <div class="subscriber-stat-item">
                                    <span class="subscriber-stat-value"><?php echo number_format($settings['subscribers']['verified']); ?></span>
                                    <span class="subscriber-stat-label">Verified</span>
                                </div>
                                
                                <div class="subscriber-stat-item">
                                    <span class="subscriber-stat-value"><?php echo number_format($settings['subscribers']['total'] - $settings['subscribers']['active']); ?></span>
                                    <span class="subscriber-stat-label">Inactive</span>
                                </div>
                            </div>
                            
                            <div class="subscriber-actions">
                                <a href="/settings/subscribers" class="btn-primary">Manage Subscribers</a>
                                <button class="btn-secondary" id="export-subscribers-btn">Export List</button>
                            </div>
                        </div>
                    </div>

                    <!-- Holder Blacklist -->
                    <div class="settings-section glassmorphism reveal">
                        <div class="settings-section-header">
                            <h2 class="settings-section-title">Holder Blacklist</h2>
                            <p class="settings-section-description">Companies excluded from outreach activities</p>
                        </div>
                        
                        <div class="settings-section-content">
                            <form method="POST" class="blacklist-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="update_blacklist">
                                
                                <div class="form-group">
                                    <label for="blacklisted_holders" class="form-label">
                                        Blacklisted Holders
                                        <small>One company name per line</small>
                                    </label>
                                    <textarea 
                                        id="blacklisted_holders" 
                                        name="blacklisted_holders" 
                                        class="form-textarea"
                                        rows="6"
                                        placeholder="Enter company names, one per line..."><?php echo htmlspecialchars(implode("\n", $settings['blacklisted_holders'])); ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-primary">Update Blacklist</button>
                                    <small class="text-muted"><?php echo count($settings['blacklisted_holders']); ?> companies currently blacklisted</small>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="settings-section glassmorphism reveal">
                        <div class="settings-section-header">
                            <h2 class="settings-section-title">System Information</h2>
                            <p class="settings-section-description">Platform status and configuration details</p>
                        </div>
                        
                        <div class="settings-section-content">
                            <div class="system-info-grid">
                                <div class="system-info-item">
                                    <label class="system-info-label">PHP Version</label>
                                    <div class="system-info-value"><?php echo $settings['system']['php_version']; ?></div>
                                </div>
                                
                                <div class="system-info-item">
                                    <label class="system-info-label">Timezone</label>
                                    <div class="system-info-value"><?php echo $settings['system']['timezone']; ?></div>
                                </div>
                                
                                <div class="system-info-item">
                                    <label class="system-info-label">Environment</label>
                                    <div class="system-info-value">
                                        <span class="env-badge <?php echo $settings['system']['environment']; ?>">
                                            <?php echo ucfirst($settings['system']['environment']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="system-info-item">
                                    <label class="system-info-label">Last Cron Run</label>
                                    <div class="system-info-value" id="last-cron-time">Loading...</div>
                                </div>
                            </div>
                            
                            <div class="system-actions">
                                <button class="btn-secondary" id="check-system-health-btn">Check System Health</button>
                                <a href="/settings/logs" class="btn-text">View Logs</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 samfedbiz.com | Owner: Quartermasters FZC | All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/settings-admin.js"></script>
</body>
</html>