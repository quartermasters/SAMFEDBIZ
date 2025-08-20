<?php
/**
 * Solicitation Detail Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /solicitations/{opp_no}
 * Displays detailed solicitation with AI summary, compliance checklist, and next actions
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

// Parse URL to get opportunity number
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathSegments = explode('/', trim($path, '/'));

// Expected format: solicitations/{opp_no}
if (count($pathSegments) < 2) {
    header('Location: /solicitations');
    exit;
}

$opp_no = urldecode($pathSegments[1]);

// Find the solicitation across all programs
$solicitation = null;
$program_code = null;
$adapter = null;

$valid_programs = array_map(function($code) {
    return \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
}, $programRegistry->getAvailablePrograms());
foreach ($valid_programs as $code) {
    $program_adapter = $programRegistry->getAdapter($code);
    if (!$program_adapter) continue;
    
    try {
        $opportunities = $program_adapter->fetchSolicitations(['opp_no' => $opp_no]);
        if (!empty($opportunities)) {
            $solicitation = $program_adapter->normalize($opportunities[0]);
            $solicitation['program'] = $code;
            $solicitation['program_name'] = $program_adapter->name();
            $program_code = $code;
            $adapter = $program_adapter;
            break;
        }
    } catch (Exception $e) {
        error_log("Error searching for {$opp_no} in {$code}: " . $e->getMessage());
    }
}

if (!$solicitation) {
    header('Location: /solicitations');
    exit;
}

// Get or generate AI summary
$ai_summary = null;
$compliance_checklist = null;
$next_actions = null;

// Check for existing summary in database
$stmt = $pdo->prepare("
    SELECT ai_summary, compliance_checklist, next_actions, last_updated
    FROM opportunity_meta 
    WHERE opp_no = ? AND program = ?
");
$stmt->execute([$opp_no, $program_code]);
$existing_meta = $stmt->fetch(PDO::FETCH_ASSOC);

$needs_refresh = !$existing_meta || 
                 (strtotime($existing_meta['last_updated']) < strtotime('-7 days'));

if ($existing_meta && !$needs_refresh) {
    $ai_summary = $existing_meta['ai_summary'];
    $compliance_checklist = json_decode($existing_meta['compliance_checklist'], true);
    $next_actions = json_decode($existing_meta['next_actions'], true);
}

// Get activity log for this opportunity
$stmt = $pdo->prepare("
    SELECT activity_type, activity_data, created_at, created_by
    FROM activity_log
    WHERE entity_type = 'solicitation' AND entity_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$opp_no]);
$activity_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related holders for this opportunity
$related_holders = [];
try {
    $all_holders = $adapter->listPrimesOrHolders();
    // In a real implementation, this would be filtered by capability match
    $related_holders = array_slice($all_holders, 0, 5);
} catch (Exception $e) {
    error_log("Error fetching related holders: " . $e->getMessage());
}

// Page title and meta
$page_title = $solicitation['title'];
$meta_description = "View details for {$solicitation['title']} ({$solicitation['opp_no']}) from {$solicitation['agency']}. AI summary, compliance checklist, and next actions.";

// Build context for SFBAI
$sfbai_context = [
    'page_type' => 'solicitation_detail',
    'opp_no' => $solicitation['opp_no'],
    'title' => $solicitation['title'],
    'agency' => $solicitation['agency'],
    'program' => $program_code,
    'program_name' => $solicitation['program_name'],
    'close_date' => $solicitation['close_date'],
    'status' => $solicitation['status'],
    'keywords' => array_merge($adapter->keywords(), ['solicitation', 'opportunity', 'compliance', 'bid'])
];

// Get CSRF token
$csrf_token = $authManager->generateCSRFToken();
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
<body class="solicitation-detail">
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
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content" class="solicitation-main">
        <!-- Solicitation Header -->
        <section class="solicitation-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li><a href="/solicitations">Solicitations</a></li>
                        <li><?php echo htmlspecialchars($solicitation['opp_no']); ?></li>
                    </ol>
                </nav>

                <div class="solicitation-header-content">
                    <div class="solicitation-meta">
                        <div class="solicitation-icon" aria-hidden="true">
                            <!-- File-text icon -->
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10,9 9,9 8,9"/>
                            </svg>
                        </div>
                        
                        <div class="solicitation-info">
                            <h1 class="solicitation-title"><?php echo htmlspecialchars($solicitation['title']); ?></h1>
                            <p class="solicitation-number"><?php echo htmlspecialchars($solicitation['opp_no']); ?></p>
                            
                            <div class="solicitation-details">
                                <span class="solicitation-program">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="7" height="7"/>
                                        <rect x="14" y="3" width="7" height="7"/>
                                        <rect x="14" y="14" width="7" height="7"/>
                                        <rect x="3" y="14" width="7" height="7"/>
                                    </svg>
                                    <?php echo htmlspecialchars($solicitation['program_name']); ?>
                                </span>
                                
                                <span class="solicitation-agency">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
                                        <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>
                                        <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>
                                        <path d="M10 6h4"/>
                                        <path d="M10 10h4"/>
                                        <path d="M10 14h4"/>
                                        <path d="M10 18h4"/>
                                    </svg>
                                    <?php echo htmlspecialchars($solicitation['agency']); ?>
                                </span>
                                
                                <span class="solicitation-close-date">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    Closes: <?php echo date('M j, Y g:i A', strtotime($solicitation['close_date'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="solicitation-actions">
                        <span class="status-badge status-<?php echo strtolower($solicitation['status']); ?>">
                            <?php echo htmlspecialchars($solicitation['status']); ?>
                        </span>
                        
                        <div class="action-buttons">
                            <a href="<?php echo htmlspecialchars($solicitation['url']); ?>" 
                               target="_blank" 
                               rel="noopener" 
                               class="btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    <polyline points="15,3 21,3 21,9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                </svg>
                                View Original
                            </a>
                            
                            <?php if ($user['role'] !== 'viewer'): ?>
                            <button class="btn-secondary" id="generate-summary-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9"/>
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                </svg>
                                Generate AI Summary
                            </button>
                            
                            <button class="btn-text" id="add-note-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Add Note
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Solicitation Content -->
        <section class="solicitation-content">
            <div class="container">
                <div class="solicitation-grid">
                    <!-- Main Content Area -->
                    <div class="solicitation-main-content">
                        <!-- AI Summary -->
                        <div class="content-card glassmorphism reveal" id="ai-summary-card">
                            <div class="card-header">
                                <h2 class="card-title">AI Summary</h2>
                                <div class="card-actions">
                                    <?php if ($user['role'] !== 'viewer'): ?>
                                    <button class="btn-text" id="refresh-summary-btn">Refresh</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="ai-summary-content">
                                <?php if ($ai_summary): ?>
                                <div class="summary-text">
                                    <?php echo nl2br(htmlspecialchars($ai_summary)); ?>
                                </div>
                                <div class="summary-meta">
                                    <small class="summary-updated">
                                        Last updated: <?php echo date('M j, Y g:i A', strtotime($existing_meta['last_updated'])); ?>
                                    </small>
                                    <small class="summary-source">
                                        Source: <a href="<?php echo htmlspecialchars($solicitation['url']); ?>" target="_blank" rel="noopener">Original Solicitation</a>
                                    </small>
                                </div>
                                <?php else: ?>
                                <div class="no-summary-message">
                                    <p>No AI summary available yet.</p>
                                    <?php if ($user['role'] !== 'viewer'): ?>
                                    <button class="btn-primary" id="generate-summary-btn">Generate Summary</button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Compliance Checklist -->
                        <div class="content-card glassmorphism reveal" id="compliance-card">
                            <div class="card-header">
                                <h2 class="card-title">Compliance Checklist</h2>
                                <div class="card-actions">
                                    <?php if ($user['role'] !== 'viewer'): ?>
                                    <button class="btn-text" id="update-checklist-btn">Update</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="compliance-content">
                                <?php if ($compliance_checklist): ?>
                                <div class="checklist-items">
                                    <?php foreach ($compliance_checklist as $item): ?>
                                    <div class="checklist-item <?php echo $item['completed'] ? 'completed' : ''; ?>">
                                        <div class="checklist-checkbox">
                                            <input type="checkbox" 
                                                   id="check-<?php echo $item['id']; ?>" 
                                                   <?php echo $item['completed'] ? 'checked' : ''; ?>
                                                   data-item-id="<?php echo $item['id']; ?>"
                                                   class="compliance-checkbox">
                                            <label for="check-<?php echo $item['id']; ?>"></label>
                                        </div>
                                        <div class="checklist-content">
                                            <span class="checklist-title"><?php echo htmlspecialchars($item['title']); ?></span>
                                            <?php if (!empty($item['description'])): ?>
                                            <span class="checklist-description"><?php echo htmlspecialchars($item['description']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item['due_date'])): ?>
                                        <span class="checklist-due-date">
                                            Due: <?php echo date('M j', strtotime($item['due_date'])); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="no-checklist-message">
                                    <p>No compliance checklist available yet.</p>
                                    <?php if ($user['role'] !== 'viewer'): ?>
                                    <button class="btn-primary" id="generate-checklist-btn">Generate Checklist</button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Next Actions -->
                        <div class="content-card glassmorphism reveal" id="next-actions-card">
                            <div class="card-header">
                                <h2 class="card-title">Next Actions</h2>
                                <div class="card-actions">
                                    <?php if ($user['role'] !== 'viewer'): ?>
                                    <button class="btn-text" id="add-action-btn">Add Action</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="next-actions-content">
                                <?php if ($next_actions): ?>
                                <div class="actions-list">
                                    <?php foreach ($next_actions as $action): ?>
                                    <div class="action-item <?php echo $action['completed'] ? 'completed' : ''; ?>">
                                        <div class="action-checkbox">
                                            <input type="checkbox" 
                                                   id="action-<?php echo $action['id']; ?>" 
                                                   <?php echo $action['completed'] ? 'checked' : ''; ?>
                                                   data-action-id="<?php echo $action['id']; ?>"
                                                   class="action-checkbox-input">
                                            <label for="action-<?php echo $action['id']; ?>"></label>
                                        </div>
                                        <div class="action-content">
                                            <span class="action-title"><?php echo htmlspecialchars($action['title']); ?></span>
                                            <?php if (!empty($action['description'])): ?>
                                            <span class="action-description"><?php echo htmlspecialchars($action['description']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="action-meta">
                                            <span class="action-priority priority-<?php echo strtolower($action['priority'] ?? 'medium'); ?>">
                                                <?php echo htmlspecialchars($action['priority'] ?? 'Medium'); ?>
                                            </span>
                                            <?php if (!empty($action['due_date'])): ?>
                                            <span class="action-due-date">
                                                <?php echo date('M j', strtotime($action['due_date'])); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="no-actions-message">
                                    <p>No next actions defined yet.</p>
                                    <?php if ($user['role'] !== 'viewer'): ?>
                                    <button class="btn-primary" id="generate-actions-btn">Generate Actions</button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Related Holders -->
                        <?php if (!empty($related_holders)): ?>
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">Related <?php echo $program_code === 'tls' ? 'Primes' : 'Holders'; ?></h2>
                                <div class="card-actions">
                                    <a href="/programs/?code=<?php echo $program_code; ?>" class="btn-text">View All</a>
                                </div>
                            </div>
                            
                            <div class="related-holders-grid">
                                <?php foreach ($related_holders as $holder): ?>
                                <div class="holder-item tilt-card" tabindex="0">
                                    <div class="holder-info">
                                        <h3 class="holder-name">
                                            <a href="/programs/<?php echo $program_code; ?>/holders/<?php echo $holder['id']; ?>">
                                                <?php echo htmlspecialchars($holder['name']); ?>
                                            </a>
                                        </h3>
                                        <p class="holder-location"><?php echo htmlspecialchars($holder['location'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="holder-actions">
                                        <a href="/programs/<?php echo $program_code; ?>/holders/<?php echo $holder['id']; ?>" class="btn-text">View Profile</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Activity Log -->
                        <?php if (!empty($activity_log)): ?>
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">Activity Log</h2>
                            </div>
                            
                            <div class="activity-timeline">
                                <?php foreach ($activity_log as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12,6 12,12 16,14"/>
                                        </svg>
                                    </div>
                                    
                                    <div class="activity-content">
                                        <div class="activity-header">
                                            <span class="activity-type"><?php echo htmlspecialchars($activity['activity_type']); ?></span>
                                            <span class="activity-date"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></span>
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
                        <?php endif; ?>
                    </div>

                    <!-- SFBAI Sidebar -->
                    <aside class="solicitation-sidebar" role="complementary" aria-label="SFBAI Assistant">
                        <div class="sfbai-panel glassmorphism">
                            <div class="sfbai-header">
                                <h2 class="sfbai-title">SFBAI Assistant</h2>
                                <p class="sfbai-context">
                                    <?php echo htmlspecialchars($solicitation['opp_no']); ?>
                                </p>
                            </div>
                            
                            <div class="sfbai-chatbox">
                                <div id="chat-response" class="chat-response" role="log" aria-live="polite" aria-label="Chat conversation history"></div>
                                
                                <div class="chat-input-container">
                                    <div class="chat-input-wrapper">
                                        <input type="text" 
                                               id="chat-input" 
                                               class="chat-input" 
                                               placeholder="Ask about this opportunity..." 
                                               aria-label="Chat with SFBAI assistant about this solicitation"
                                               aria-describedby="chat-help-text">
                                        <button class="chat-send" aria-label="Send message">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="22" y1="2" x2="11" y2="13"/>
                                                <polygon points="22,2 15,22 11,13 2,9"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="chat-help-text" class="chat-help-text">
                                        Try: "Summarize requirements" or "Show compliance steps"
                                    </p>
                                </div>
                            </div>
                            
                            <div id="chat-actions" class="chat-actions" style="display: none;"></div>
                        </div>
                    </aside>
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
    <script>
        // Pass SFBAI context to JavaScript
        window.sfbaiContext = <?php echo json_encode($sfbai_context); ?>;
        window.solicitationData = {
            opp_no: <?php echo json_encode($solicitation['opp_no']); ?>,
            title: <?php echo json_encode($solicitation['title']); ?>,
            program: <?php echo json_encode($program_code); ?>,
            url: <?php echo json_encode($solicitation['url']); ?>
        };
    </script>
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/sfbai-chat.js"></script>
    <script src="/js/solicitation-detail.js"></script>
</body>
</html>
