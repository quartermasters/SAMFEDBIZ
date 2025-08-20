<?php
/**
 * Holder Profile Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /programs/{code}/holders/{id}
 * Displays holder profile with micro-catalog, activity log, and action buttons
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

// Parse URL path to get program and holder ID
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathSegments = explode('/', trim($path, '/'));

// Expected format: programs/{code}/holders/{id}
if (count($pathSegments) < 4 || $pathSegments[2] !== 'holders') {
    header('Location: /');
    exit;
}

$program_code_raw = strtolower($pathSegments[1]);
$normalized_code = \SamFedBiz\Core\ProgramRegistry::normalizeCode($program_code_raw);
$program_code = \SamFedBiz\Core\ProgramRegistry::getDisplayCode($normalized_code);
$holder_id = intval($pathSegments[3]);

// Validate program via registry
if (!$programRegistry->getAdapter($program_code)) {
    header('Location: /');
    exit;
}

// Get adapter instance
$adapter = $programRegistry->getAdapter($program_code);
if (!$adapter) {
    header('Location: /');
    exit;
}

// Get holder details
$holder = null;
try {
    $all_holders = $adapter->listPrimesOrHolders();
    foreach ($all_holders as $h) {
        if ($h['id'] === $holder_id) {
            $holder = $h;
            break;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching holder {$holder_id} for {$program_code}: " . $e->getMessage());
}

if (!$holder) {
    header('Location: /programs/?code=' . urlencode($program_code));
    exit;
}

// Get holder metadata
$stmt = $pdo->prepare("
    SELECT h.*, hm.website, hm.phone, hm.address, hm.cage_code, hm.socioeconomic_status,
           hm.certifications, hm.capabilities, hm.specialties, hm.past_performance
    FROM holders h
    LEFT JOIN holder_meta hm ON h.id = hm.holder_id
    WHERE h.program = ? AND h.external_id = ?
");
$stmt->execute([$program_code, $holder_id]);
$holder_meta = $stmt->fetch(PDO::FETCH_ASSOC);

// Merge adapter data with database metadata
if ($holder_meta) {
    $holder = array_merge($holder, $holder_meta);
}

// Get activity log
$stmt = $pdo->prepare("
    SELECT activity_type, activity_data, created_at, created_by
    FROM activity_log
    WHERE entity_type = 'holder' AND entity_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$holder_id]);
$activity_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related opportunities
$recent_opportunities = [];
try {
    $opportunities = $adapter->fetchSolicitations(['holder_id' => $holder_id, 'limit' => 5]);
    foreach ($opportunities as $opp) {
        $recent_opportunities[] = $adapter->normalize($opp);
    }
} catch (Exception $e) {
    error_log("Error fetching opportunities for holder {$holder_id}: " . $e->getMessage());
}

// Get micro-catalog items (for TLS) or capability sheets (for OASIS+/SEWP)
$catalog_items = [];
if ($program_code === 'tls') {
    // Get prime catalog items
    $stmt = $pdo->prepare("
        SELECT part_number, description, category, price_range, lead_time_days, 
               use_cases, kit_compatibility, specifications
        FROM prime_catalog
        WHERE holder_id = ? AND program = ?
        ORDER BY category, part_number
        LIMIT 50
    ");
    $stmt->execute([$holder_id, $program_code]);
    $catalog_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Get capability categories for OASIS+/SEWP
    $capabilities = !empty($holder['capabilities']) ? 
        (is_array($holder['capabilities']) ? $holder['capabilities'] : json_decode($holder['capabilities'], true)) : [];
}

// Page title and context
$entity_type = $program_code === 'tls' ? 'Prime' : 'Holder';
$page_title = $holder['name'] . " - " . $adapter->name() . " " . $entity_type;
$meta_description = "Detailed profile for {$holder['name']} in the {$adapter->name()} program. View capabilities, catalog, and contact information.";

// Build context for SFBAI
$sfbai_context = [
    'program' => $program_code,
    'program_name' => $adapter->name(),
    'holder_id' => $holder_id,
    'holder_name' => $holder['name'],
    'entity_type' => strtolower($entity_type),
    'page_type' => 'holder_profile',
    'keywords' => array_merge($adapter->keywords(), ['profile', 'capabilities', 'catalog'])
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
    <link rel="stylesheet" href="/styles/print.css" media="print">
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://unpkg.com/gsap@3.12.2/dist/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.27/dist/lenis.min.js"></script>
</head>
<body class="holder-profile">
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
                <a href="/programs/tls" class="nav-link <?php echo $program_code === 'tls' ? 'active' : ''; ?>">TLS</a>
                <a href="/programs/oasis+" class="nav-link <?php echo $program_code === 'oasis+' ? 'active' : ''; ?>">OASIS+</a>
                <a href="/programs/sewp" class="nav-link <?php echo $program_code === 'sewp' ? 'active' : ''; ?>">SEWP</a>
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
    <main id="main-content" class="holder-main">
        <!-- Holder Header -->
        <section class="holder-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li><a href="/programs/?code=<?php echo urlencode($program_code); ?>"><?php echo htmlspecialchars($adapter->name()); ?></a></li>
                        <li><?php echo htmlspecialchars($holder['name']); ?></li>
                    </ol>
                </nav>

                <div class="holder-header-content">
                    <div class="holder-meta">
                        <div class="holder-icon" aria-hidden="true">
                            <?php if ($program_code === 'tls'): ?>
                                <!-- Shield icon for TLS Prime -->
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                            <?php else: ?>
                                <!-- Building icon for OASIS+/SEWP Holders -->
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
                                    <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>
                                    <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>
                                    <path d="M10 6h4"/>
                                    <path d="M10 10h4"/>
                                    <path d="M10 14h4"/>
                                    <path d="M10 18h4"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        
                        <div class="holder-info">
                            <h1 class="holder-name"><?php echo htmlspecialchars($holder['name']); ?></h1>
                            <p class="holder-type"><?php echo htmlspecialchars($adapter->name() . ' ' . $entity_type); ?></p>
                            
                            <div class="holder-details">
                                <?php if (!empty($holder['location'])): ?>
                                <span class="holder-location">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <?php echo htmlspecialchars($holder['location']); ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($holder['cage_code'])): ?>
                                <span class="holder-cage">
                                    CAGE: <code><?php echo htmlspecialchars($holder['cage_code']); ?></code>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($holder['contract_number'])): ?>
                                <span class="holder-contract">
                                    Contract: <code><?php echo htmlspecialchars($holder['contract_number']); ?></code>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="holder-actions">
                        <?php if ($user['role'] !== 'viewer'): ?>
                        <button class="btn-primary" id="draft-email-btn" data-holder-id="<?php echo $holder_id; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22,2 15,22 11,13 2,9"/>
                            </svg>
                            Draft Email
                        </button>
                        
                        <button class="btn-secondary" id="schedule-meeting-btn" data-holder-id="<?php echo $holder_id; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            Schedule Meeting
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn-text" id="print-catalog-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6,9 6,2 18,2 18,9"/>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            Print Profile
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Holder Content -->
        <section class="holder-content">
            <div class="container">
                <div class="holder-grid">
                    <!-- Main Content Area -->
                    <div class="holder-main-content">
                        <!-- Capabilities/Status Overview -->
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">Overview</h2>
                                <span class="status-badge status-<?php echo strtolower($holder['status'] ?? 'active'); ?>">
                                    <?php echo htmlspecialchars($holder['status'] ?? 'Active'); ?>
                                </span>
                            </div>
                            
                            <div class="holder-overview">
                                <?php if (!empty($holder['socioeconomic_status'])): ?>
                                <div class="overview-section">
                                    <h3>Socioeconomic Status</h3>
                                    <div class="status-tags">
                                        <?php 
                                        $statuses = is_array($holder['socioeconomic_status']) ? 
                                            $holder['socioeconomic_status'] : 
                                            explode(',', $holder['socioeconomic_status']);
                                        foreach ($statuses as $status): 
                                        ?>
                                        <span class="tag"><?php echo htmlspecialchars(trim($status)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($holder['certifications'])): ?>
                                <div class="overview-section">
                                    <h3>Certifications</h3>
                                    <div class="certification-tags">
                                        <?php 
                                        $certs = is_array($holder['certifications']) ? 
                                            $holder['certifications'] : 
                                            explode(',', $holder['certifications']);
                                        foreach ($certs as $cert): 
                                        ?>
                                        <span class="cert-tag"><?php echo htmlspecialchars(trim($cert)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($holder['website'])): ?>
                                <div class="overview-section">
                                    <h3>Website</h3>
                                    <a href="<?php echo htmlspecialchars($holder['website']); ?>" 
                                       target="_blank" 
                                       rel="noopener" 
                                       class="website-link">
                                        <?php echo htmlspecialchars($holder['website']); ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                            <polyline points="15,3 21,3 21,9"/>
                                            <line x1="10" y1="14" x2="21" y2="3"/>
                                        </svg>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Micro-Catalog or Capability Sheets -->
                        <div class="content-card glassmorphism reveal" id="catalog-section">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <?php echo $program_code === 'tls' ? 'Micro-Catalog' : 'Capability Sheet'; ?>
                                </h2>
                                <div class="card-actions">
                                    <button class="btn-text" id="export-catalog">Export CSV</button>
                                </div>
                            </div>
                            
                            <?php if ($program_code === 'tls' && !empty($catalog_items)): ?>
                            <!-- TLS Prime Catalog -->
                            <div class="catalog-grid">
                                <?php foreach ($catalog_items as $item): ?>
                                <div class="catalog-item tilt-card" tabindex="0">
                                    <div class="catalog-header">
                                        <h3 class="catalog-part-number"><?php echo htmlspecialchars($item['part_number']); ?></h3>
                                        <span class="catalog-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                    </div>
                                    
                                    <p class="catalog-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                    
                                    <div class="catalog-meta">
                                        <?php if (!empty($item['price_range'])): ?>
                                        <span class="catalog-price"><?php echo htmlspecialchars($item['price_range']); ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($item['lead_time_days'])): ?>
                                        <span class="catalog-lead-time"><?php echo $item['lead_time_days']; ?> days</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($item['kit_compatibility'])): ?>
                                    <div class="catalog-kits">
                                        <strong>Kit Compatible:</strong> <?php echo htmlspecialchars($item['kit_compatibility']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php elseif ($program_code !== 'tls' && !empty($capabilities)): ?>
                            <!-- OASIS+/SEWP Capability Categories -->
                            <div class="capability-grid">
                                <?php foreach ($capabilities as $capability): ?>
                                <div class="capability-item tilt-card" tabindex="0">
                                    <h3 class="capability-title"><?php echo htmlspecialchars($capability); ?></h3>
                                    <div class="capability-actions">
                                        <button class="btn-text request-info-btn" data-capability="<?php echo htmlspecialchars($capability); ?>">
                                            Request Info
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php else: ?>
                            <div class="no-data-message">
                                <p>No <?php echo $program_code === 'tls' ? 'catalog items' : 'capabilities'; ?> available for this <?php echo strtolower($entity_type); ?>.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recent Opportunities -->
                        <?php if (!empty($recent_opportunities)): ?>
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">Related Opportunities</h2>
                                <div class="card-actions">
                                    <a href="/solicitations?holder=<?php echo $holder_id; ?>" class="btn-text">View All</a>
                                </div>
                            </div>
                            
                            <div class="opportunities-list">
                                <?php foreach ($recent_opportunities as $opp): ?>
                                <div class="opportunity-item tilt-card" tabindex="0">
                                    <div class="opportunity-header">
                                        <h3 class="opportunity-title">
                                            <a href="<?php echo htmlspecialchars($opp['url']); ?>" 
                                               target="_blank" 
                                               rel="noopener">
                                                <?php echo htmlspecialchars($opp['title']); ?>
                                            </a>
                                        </h3>
                                        <span class="opportunity-number"><?php echo htmlspecialchars($opp['opp_no']); ?></span>
                                    </div>
                                    
                                    <div class="opportunity-meta">
                                        <span class="opportunity-agency"><?php echo htmlspecialchars($opp['agency']); ?></span>
                                        <span class="opportunity-close-date">
                                            Closes: <?php echo date('M j, Y', strtotime($opp['close_date'])); ?>
                                        </span>
                                        <span class="status-badge status-<?php echo strtolower($opp['status']); ?>">
                                            <?php echo htmlspecialchars($opp['status']); ?>
                                        </span>
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
                                <div class="card-actions">
                                    <button class="btn-text" id="add-note-btn">Add Note</button>
                                </div>
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
                                            } else {
                                                echo htmlspecialchars($activity['activity_data']);
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
                    <aside class="holder-sidebar" role="complementary" aria-label="SFBAI Assistant">
                        <div class="sfbai-panel glassmorphism">
                            <div class="sfbai-header">
                                <h2 class="sfbai-title">SFBAI Assistant</h2>
                                <p class="sfbai-context">
                                    <?php echo htmlspecialchars($entity_type . ': ' . $holder['name']); ?>
                                </p>
                            </div>
                            
                            <div class="sfbai-chatbox">
                                <div id="chat-response" class="chat-response" role="log" aria-live="polite" aria-label="Chat conversation history"></div>
                                
                                <div class="chat-input-container">
                                    <div class="chat-input-wrapper">
                                        <input type="text" 
                                               id="chat-input" 
                                               class="chat-input" 
                                               placeholder="Ask about <?php echo htmlspecialchars($holder['name']); ?>..." 
                                               aria-label="Chat with SFBAI assistant about <?php echo htmlspecialchars($holder['name']); ?>"
                                               aria-describedby="chat-help-text">
                                        <button class="chat-send" aria-label="Send message">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="22" y1="2" x2="11" y2="13"/>
                                                <polygon points="22,2 15,22 11,13 2,9"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="chat-help-text" class="chat-help-text">
                                        Try: "Draft email" or "Show capabilities"
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
    </script>
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/sfbai-chat.js"></script>
    <script src="/js/holder-profile.js"></script>
</body>
</html>
