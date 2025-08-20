<?php
/**
 * Program Overview Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Displays program details with holders, solicitations, research tables
 * and inline SFBAI panel for contextual assistance
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

// Get program code from URL and normalize/alias
$raw_program_code = $_GET['code'] ?? 'tls';
$normalized_code = \SamFedBiz\Core\ProgramRegistry::normalizeCode($raw_program_code);
$program_code = \SamFedBiz\Core\ProgramRegistry::getDisplayCode($normalized_code);

// Get adapter instance
$adapter = $programRegistry->getAdapter($program_code);
if (!$adapter) {
    header('Location: /');
    exit;
}

// Get program metadata
$program_name = $adapter->name();
$program_keywords = implode(', ', $adapter->keywords());

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Get holders/primes
$holders = [];
try {
    $all_holders = $adapter->listPrimesOrHolders();
    $holders = array_slice($all_holders, $offset, $per_page);
    $total_holders = count($all_holders);
} catch (Exception $e) {
    error_log("Error fetching holders for {$program_code}: " . $e->getMessage());
    $total_holders = 0;
}

// Get recent solicitations
$solicitations = [];
try {
    $recent_solicitations = $adapter->fetchSolicitations(['limit' => 10, 'recent' => true]);
    foreach ($recent_solicitations as $opp) {
        $solicitations[] = $adapter->normalize($opp);
    }
} catch (Exception $e) {
    error_log("Error fetching solicitations for {$program_code}: " . $e->getMessage());
}

// Get recent research docs
$research_docs = [];
$stmt = $pdo->prepare("
    SELECT id, title, doc_type, source_url, created_at, tags
    FROM research_docs 
    WHERE JSON_CONTAINS(tags, JSON_QUOTE(?))
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$program_code]);
$research_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate pagination
$total_pages = ceil($total_holders / $per_page);

// Page title and meta
$page_title = $program_name . " Program Overview";
$meta_description = "Federal BD intelligence for {$program_name}. View contract holders, opportunities, and research documents.";

// Build context for SFBAI
$sfbai_context = [
    'program' => $program_code,
    'program_name' => $program_name,
    'total_holders' => $total_holders,
    'total_solicitations' => count($solicitations),
    'keywords' => $adapter->keywords()
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
<body class="program-overview">
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
    <main id="main-content" class="program-main">
        <!-- Program Header -->
        <section class="program-header">
            <div class="container">
                <div class="program-header-content">
                    <div class="program-meta">
                        <div class="program-icon" aria-hidden="true">
                            <?php if ($program_code === 'tls'): ?>
                                <!-- Shield icon for TLS -->
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                            <?php elseif ($program_code === 'oasis+'): ?>
                                <!-- Layout-grid icon for OASIS+ -->
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                </svg>
                            <?php else: ?>
                                <!-- Device-desktop icon for SEWP -->
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21"/>
                                    <line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="program-info">
                            <h1 class="program-title"><?php echo htmlspecialchars($program_name); ?></h1>
                            <p class="program-keywords"><?php echo htmlspecialchars($program_keywords); ?></p>
                        </div>
                    </div>
                    
                    <div class="program-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($total_holders); ?></span>
                            <span class="stat-label"><?php echo $program_code === 'tls' ? 'Primes' : 'Holders'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo count($solicitations); ?></span>
                            <span class="stat-label">Recent Opps</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo count($research_docs); ?></span>
                            <span class="stat-label">Research</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Program Content -->
        <section class="program-content">
            <div class="container">
                <div class="program-grid">
                    <!-- Main Content Area -->
                    <div class="program-main-content">
                        <!-- Holders/Primes Table -->
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <?php echo $program_code === 'tls' ? 'Prime Contractors' : 'Contract Holders'; ?>
                                </h2>
                                <div class="card-actions">
                                    <button class="btn-text" id="export-holders" aria-label="Export holders to CSV">Export</button>
                                </div>
                            </div>
                            
                            <div class="table-container">
                                <table class="data-table" role="table" aria-label="<?php echo $program_code === 'tls' ? 'Prime contractors' : 'Contract holders'; ?> table">
                                    <thead>
                                        <tr>
                                            <th scope="col">
                                                <button class="table-sort" data-column="name" aria-label="Sort by name">
                                                    Company Name
                                                    <span class="sort-indicator" aria-hidden="true"></span>
                                                </button>
                                            </th>
                                            <th scope="col">
                                                <button class="table-sort" data-column="location" aria-label="Sort by location">
                                                    Location
                                                    <span class="sort-indicator" aria-hidden="true"></span>
                                                </button>
                                            </th>
                                            <?php if ($program_code !== 'tls'): ?>
                                            <th scope="col">
                                                <button class="table-sort" data-column="contract" aria-label="Sort by contract">
                                                    Contract #
                                                    <span class="sort-indicator" aria-hidden="true"></span>
                                                </button>
                                            </th>
                                            <?php endif; ?>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($holders as $holder): ?>
                                        <tr class="tilt-card" tabindex="0">
                                            <td>
                                                <div class="holder-info">
                                                    <span class="holder-name"><?php echo htmlspecialchars($holder['name']); ?></span>
                                                    <?php if (!empty($holder['capabilities'])): ?>
                                                    <span class="holder-capabilities"><?php echo htmlspecialchars(implode(', ', array_slice($holder['capabilities'], 0, 3))); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($holder['location'] ?? 'N/A'); ?></td>
                                            <?php if ($program_code !== 'tls'): ?>
                                            <td>
                                                <code class="contract-number"><?php echo htmlspecialchars($holder['contract_number'] ?? 'N/A'); ?></code>
                                            </td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($holder['status'] ?? 'active'); ?>">
                                                    <?php echo htmlspecialchars($holder['status'] ?? 'Active'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="/programs/<?php echo $program_code; ?>/holders/<?php echo $holder['id']; ?>" 
                                                       class="btn-text" 
                                                       aria-label="View <?php echo htmlspecialchars($holder['name']); ?> profile">
                                                        View
                                                    </a>
                                                    <?php if ($user['role'] !== 'viewer'): ?>
                                                    <button class="btn-text draft-email-btn" 
                                                            data-holder-id="<?php echo $holder['id']; ?>"
                                                            aria-label="Draft email to <?php echo htmlspecialchars($holder['name']); ?>">
                                                        Email
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($holders)): ?>
                                        <tr>
                                            <td colspan="<?php echo $program_code === 'tls' ? '4' : '5'; ?>" class="no-data">
                                                <div class="no-data-message">
                                                    <p>No <?php echo $program_code === 'tls' ? 'prime contractors' : 'contract holders'; ?> found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <nav aria-label="Pagination navigation" role="navigation">
                                    <ul class="pagination-list">
                                        <?php if ($page > 1): ?>
                                        <li>
                                            <a href="?code=<?php echo $program_code; ?>&page=<?php echo $page - 1; ?>" 
                                               class="pagination-link" 
                                               aria-label="Go to previous page">
                                                Previous
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li>
                                            <a href="?code=<?php echo $program_code; ?>&page=<?php echo $i; ?>" 
                                               class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>"
                                               aria-label="Go to page <?php echo $i; ?>"
                                               <?php echo $i === $page ? 'aria-current="page"' : ''; ?>>
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                        <li>
                                            <a href="?code=<?php echo $program_code; ?>&page=<?php echo $page + 1; ?>" 
                                               class="pagination-link" 
                                               aria-label="Go to next page">
                                                Next
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recent Solicitations -->
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">Recent Opportunities</h2>
                                <div class="card-actions">
                                    <a href="/solicitations?program=<?php echo $program_code; ?>" class="btn-text">View All</a>
                                </div>
                            </div>
                            
                            <div class="solicitations-list">
                                <?php foreach ($solicitations as $solicitation): ?>
                                <div class="solicitation-item tilt-card" tabindex="0">
                                    <div class="solicitation-header">
                                        <h3 class="solicitation-title">
                                            <a href="<?php echo htmlspecialchars($solicitation['url']); ?>" 
                                               target="_blank" 
                                               rel="noopener"
                                               aria-label="View solicitation: <?php echo htmlspecialchars($solicitation['title']); ?>">
                                                <?php echo htmlspecialchars($solicitation['title']); ?>
                                            </a>
                                        </h3>
                                        <span class="solicitation-number"><?php echo htmlspecialchars($solicitation['opp_no']); ?></span>
                                    </div>
                                    
                                    <div class="solicitation-meta">
                                        <span class="solicitation-agency"><?php echo htmlspecialchars($solicitation['agency']); ?></span>
                                        <span class="solicitation-close-date">
                                            Closes: <?php echo date('M j, Y', strtotime($solicitation['close_date'])); ?>
                                        </span>
                                        <span class="status-badge status-<?php echo strtolower($solicitation['status']); ?>">
                                            <?php echo htmlspecialchars($solicitation['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($solicitations)): ?>
                                <div class="no-data-message">
                                    <p>No recent opportunities found for <?php echo htmlspecialchars($program_name); ?>.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Research Documents -->
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">Research Documents</h2>
                                <div class="card-actions">
                                    <a href="/research?program=<?php echo $program_code; ?>" class="btn-text">View All</a>
                                </div>
                            </div>
                            
                            <div class="research-list">
                                <?php foreach ($research_docs as $doc): ?>
                                <div class="research-item tilt-card" tabindex="0">
                                    <div class="research-meta">
                                        <span class="research-type"><?php echo htmlspecialchars($doc['doc_type']); ?></span>
                                        <span class="research-date"><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></span>
                                    </div>
                                    <h3 class="research-title">
                                        <a href="<?php echo htmlspecialchars($doc['source_url']); ?>" 
                                           target="_blank" 
                                           rel="noopener"
                                           aria-label="View research document: <?php echo htmlspecialchars($doc['title']); ?>">
                                            <?php echo htmlspecialchars($doc['title']); ?>
                                        </a>
                                    </h3>
                                    <?php if (!empty($doc['tags'])): ?>
                                    <div class="research-tags">
                                        <?php 
                                        $tags = json_decode($doc['tags'], true);
                                        foreach (array_slice($tags, 0, 3) as $tag): 
                                        ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($research_docs)): ?>
                                <div class="no-data-message">
                                    <p>No research documents found for <?php echo htmlspecialchars($program_name); ?>.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- SFBAI Sidebar -->
                    <aside class="program-sidebar" role="complementary" aria-label="SFBAI Assistant">
                        <div class="sfbai-panel glassmorphism">
                            <div class="sfbai-header">
                                <h2 class="sfbai-title">SFBAI Assistant</h2>
                                <p class="sfbai-context">Program: <?php echo htmlspecialchars($program_name); ?></p>
                            </div>
                            
                            <div class="sfbai-chatbox">
                                <div id="chat-response" class="chat-response" role="log" aria-live="polite" aria-label="Chat conversation history"></div>
                                
                                <div class="chat-input-container">
                                    <div class="chat-input-wrapper">
                                        <input type="text" 
                                               id="chat-input" 
                                               class="chat-input" 
                                               placeholder="Ask about <?php echo htmlspecialchars($program_name); ?>..." 
                                               aria-label="Chat with SFBAI assistant about <?php echo htmlspecialchars($program_name); ?>"
                                               aria-describedby="chat-help-text">
                                        <button class="chat-send" aria-label="Send message">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="22" y1="2" x2="11" y2="13"/>
                                                <polygon points="22,2 15,22 11,13 2,9"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="chat-help-text" class="chat-help-text">
                                        Try: "/brief <?php echo $program_code; ?>" or "Show me recent opportunities"
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
    <script src="/js/program-overview.js"></script>
</body>
</html>
