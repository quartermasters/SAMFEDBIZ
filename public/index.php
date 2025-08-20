<?php
/**
 * Dashboard - Main platform entry point
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

require_once __DIR__ . '/../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Core\ProgramRegistry;

// Initialize managers
$authManager = new AuthManager($pdo);
$programRegistry = new ProgramRegistry($pdo);

// Check authentication - redirect to login if not authenticated
if (!$authManager->isAuthenticated()) {
    $redirect_url = '/auth/login.php';
    if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
        $redirect_url .= '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    }
    header('Location: ' . $redirect_url);
    exit;
}

$user = $authManager->getCurrentUser();

// Get CSRF token for API calls
$csrf_token = $authManager->generateCsrfToken();

// Dashboard data
$dashboard_stats = [
    'total_programs' => count($programRegistry->getAvailablePrograms()),
    'active_programs' => count(array_filter($programRegistry->getAvailablePrograms(), function($code) use ($programRegistry) {
        return $programRegistry->isProgramEnabled($code);
    })),
    'total_holders' => 0,
    'opportunities_closing_soon' => 0
];

// Get recent activity
$recent_activity = [];
try {
    $stmt = $pdo->prepare("
        SELECT activity_type, entity_type, entity_id, activity_data, created_at, created_by
        FROM activity_log 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Dashboard activity error: " . $e->getMessage());
}

$page_title = "Dashboard";
$meta_description = "Federal BD intelligence platform dashboard for TLS, OASIS+, and SEWP programs";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title><?php echo htmlspecialchars($page_title); ?> | samfedbiz.com</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/styles/main.css">
    <link rel="stylesheet" href="/styles/hero.css">
    <link rel="stylesheet" href="/styles/print.css" media="print">
    
    <!-- GSAP 3.x -->
    <script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js"></script>
    
    <!-- Lenis Smooth Scroll -->
    <script src="https://cdn.jsdelivr.net/npm/@studio-freight/lenis/dist/lenis.min.js"></script>
</head>

<body class="dashboard">
    <!-- Navigation -->
    <nav class="nav-main" role="navigation">
        <div class="nav-container">
            <div class="nav-brand">
                <h1>samfedbiz</h1>
                <span class="nav-tagline">Federal BD Intelligence</span>
            </div>
            
            <div class="nav-links">
                <a href="/" class="nav-link active" aria-current="page">Dashboard</a>
                <a href="/programs/tls" class="nav-link">TLS</a>
                <a href="/programs/oasis+" class="nav-link">OASIS+</a>
                <a href="/programs/sewp" class="nav-link">SEWP</a>
                <a href="/briefs" class="nav-link">Briefs</a>
                <a href="/research" class="nav-link">Research</a>
                <?php if (in_array($user['role'], ['admin', 'ops'])): ?>
                <a href="/analytics" class="nav-link">Analytics</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="/settings" class="nav-link">Settings</a>
                <?php endif; ?>
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section with SFBAI Chatbox -->
    <section class="hero" role="main">
        <div class="hero-container">
            <!-- Left Column: Platform Overview -->
            <div class="hero-content">
                <div class="hero-header">
                    <h1 class="hero-title">Federal BD Intelligence Platform</h1>
                    <p class="hero-subtitle">Streamlined access to TLS, OASIS+, and SEWP contract opportunities</p>
                </div>
                
                <div class="hero-stats">
                    <div class="stat-card reveal">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $dashboard_stats['active_programs']; ?></span>
                            <span class="stat-label">Active Programs</span>
                        </div>
                    </div>
                    
                    <div class="stat-card reveal">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($dashboard_stats['total_holders']); ?></span>
                            <span class="stat-label">Contract Holders</span>
                        </div>
                    </div>
                    
                    <div class="stat-card reveal">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12,6 12,12 16,14"/>
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo number_format($dashboard_stats['opportunities_closing_soon']); ?></span>
                            <span class="stat-label">Closing Soon</span>
                        </div>
                    </div>
                </div>

                <!-- Program Cards -->
                <div class="program-grid">
                    <?php 
                    $program_info = [
                        'tls' => ['name' => 'Tactical Learning Systems', 'desc' => 'Training solutions and capability development'],
                        'oasis+' => ['name' => 'OASIS+ Unrestricted', 'desc' => 'Professional services and IT solutions'],
                        'sewp' => ['name' => 'Solutions for Enterprise-Wide Procurement', 'desc' => 'IT hardware, software, and services']
                    ];
                    
                    foreach ($programRegistry->getAvailablePrograms() as $code):
                        if (!$programRegistry->isProgramEnabled($code)) continue;
                        $display_code = \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
                        $info = $program_info[$display_code] ?? ['name' => strtoupper($display_code), 'desc' => 'Federal contract vehicle'];
                    ?>
                    <div class="program-card tilt-card reveal" tabindex="0">
                        <div class="program-icon">
                            <?php if ($display_code === 'tls'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
                            </svg>
                            <?php elseif ($display_code === 'oasis+'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                            </svg>
                            <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/>
                                <line x1="7" y1="2" x2="7" y2="22"/>
                                <line x1="17" y1="2" x2="17" y2="22"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <line x1="2" y1="7" x2="7" y2="7"/>
                                <line x1="2" y1="17" x2="7" y2="17"/>
                                <line x1="17" y1="17" x2="22" y2="17"/>
                                <line x1="17" y1="7" x2="22" y2="7"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <div class="program-content">
                            <h3 class="program-title"><?php echo htmlspecialchars($info['name']); ?></h3>
                            <p class="program-description"><?php echo htmlspecialchars($info['desc']); ?></p>
                        </div>
                        <div class="program-actions">
                            <a href="/programs/<?php echo $display_code; ?>" class="btn-primary">
                                Explore Program
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="7" y1="17" x2="17" y2="7"/>
                                    <polyline points="7,7 17,7 17,17"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column: SFBAI Chatbox -->
            <div class="hero-chatbox">
                <div class="chatbox glassmorphism reveal">
                    <div class="chatbox-header">
                        <div class="chatbox-title">
                            <span class="chat-icon">🤖</span>
                            <h2>SFBAI Assistant</h2>
                        </div>
                        <div class="chatbox-status">
                            <span class="status-indicator online"></span>
                            <span class="status-text">Online</span>
                        </div>
                    </div>
                    
                    <div class="chatbox-content">
                        <div id="chat-response" class="chat-response" role="log" aria-live="polite" aria-label="Chat conversation history">
                            <div class="welcome-message">
                                <p>👋 Hello <?php echo htmlspecialchars($user['name']); ?>! I'm SFBAI, your Federal BD intelligence assistant.</p>
                                <p>I can help you with:</p>
                                <ul>
                                    <li>Finding contract opportunities</li>
                                    <li>Analyzing solicitations</li>
                                    <li>Drafting outreach emails</li>
                                    <li>Scheduling meetings</li>
                                    <li>Researching contract holders</li>
                                </ul>
                                <p>Try typing <code>/help</code> to see available commands!</p>
                            </div>
                        </div>
                        
                        <div class="chat-input-container">
                            <div class="chat-input-wrapper">
                                <input type="text" 
                                       id="chat-input" 
                                       class="chat-input" 
                                       placeholder="Ask me anything about federal BD..." 
                                       aria-label="Chat with SFBAI assistant"
                                       aria-describedby="chat-help-text"
                                       autocomplete="off">
                                <button class="chat-send" aria-label="Send message">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="22" y1="2" x2="11" y2="13"/>
                                        <polygon points="22,2 15,22 11,13 2,9"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="chat-actions" style="display: none;"></div>
                            <p id="chat-help-text" class="chat-help-text">
                                Try: <button class="chat-help">/opps closing soon</button> or <button class="chat-help">/holders tls</button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Activity Section -->
    <?php if (!empty($recent_activity)): ?>
    <section class="recent-activity">
        <div class="container">
            <div class="content-card glassmorphism reveal">
                <div class="card-header">
                    <h2 class="card-title">Recent Activity</h2>
                    <a href="/analytics" class="btn-text">View All</a>
                </div>
                
                <div class="activity-timeline">
                    <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12,6 12,12 16,14"/>
                            </svg>
                        </div>
                        
                        <div class="activity-content">
                            <div class="activity-header">
                                <span class="activity-type"><?php echo htmlspecialchars($activity['activity_type']); ?></span>
                                <span class="activity-date"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></span>
                            </div>
                            
                            <?php if (!empty($activity['activity_data'])): ?>
                            <div class="activity-details">
                                <?php 
                                $data = json_decode($activity['activity_data'], true);
                                if (is_array($data) && isset($data['message'])) {
                                    echo htmlspecialchars($data['message']);
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($activity['created_by'])): ?>
                            <div class="activity-author">by <?php echo htmlspecialchars($activity['created_by']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 samfedbiz.com | Owner: Quartermasters FZC | All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Pass user context to JavaScript
        window.userContext = {
            name: <?php echo json_encode($user['name']); ?>,
            role: <?php echo json_encode($user['role']); ?>,
            permissions: {
                canViewAnalytics: <?php echo json_encode(in_array($user['role'], ['admin', 'ops'])); ?>,
                canManageSettings: <?php echo json_encode($user['role'] === 'admin'); ?>
            }
        };

        // SFBAI context for dashboard
        window.sfbaiContext = {
            page_type: 'dashboard',
            user_role: <?php echo json_encode($user['role']); ?>,
            active_programs: <?php echo json_encode(array_map(function($code) use ($programRegistry) {
                return \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
            }, array_filter($programRegistry->getAvailablePrograms(), function($code) use ($programRegistry) {
                return $programRegistry->isProgramEnabled($code);
            }))); ?>,
            keywords: ['dashboard', 'overview', 'programs', 'opportunities', 'federal', 'contracting']
        };
    </script>
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/sfbai-chat.js"></script>
</body>
</html>