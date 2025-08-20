<?php
/**
 * Solicitations List Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /solicitations/
 * Displays filtered list of opportunities with AI summaries and compliance checklists
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

// Get filter parameters
$program_filter_raw = $_GET['program'] ?? '';
$program_filter_norm = \SamFedBiz\Core\ProgramRegistry::normalizeCode($program_filter_raw);
$program_filter = $program_filter_raw === '' ? '' : \SamFedBiz\Core\ProgramRegistry::getDisplayCode($program_filter_norm);
$status_filter = $_GET['status'] ?? 'open';
$agency_filter = $_GET['agency'] ?? '';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'close_date';
$order = $_GET['order'] ?? 'asc';

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Available program codes (display form)
$valid_programs = array_map(function($code) {
    return \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
}, $programRegistry->getAvailablePrograms());
if ($program_filter && !in_array($program_filter, $valid_programs, true)) {
    $program_filter = '';
}

// Get solicitations from all active programs
$all_solicitations = [];
$total_count = 0;

foreach ($valid_programs as $program_code) {
    if ($program_filter && $program_filter !== $program_code) {
        continue;
    }
    
    $adapter = $programRegistry->getAdapter($program_code);
    if (!$adapter) continue;
    
    try {
        $filters = [
            'status' => $status_filter,
            'search' => $search,
            'agency' => $agency_filter,
            'limit' => 1000 // Get all for proper sorting
        ];
        
        $opportunities = $adapter->fetchSolicitations($filters);
        foreach ($opportunities as $opp) {
            $normalized = $adapter->normalize($opp);
            $normalized['program'] = $program_code;
            $normalized['program_name'] = $adapter->name();
            $all_solicitations[] = $normalized;
        }
    } catch (Exception $e) {
        error_log("Error fetching solicitations for {$program_code}: " . $e->getMessage());
    }
}

// Sort solicitations
usort($all_solicitations, function($a, $b) use ($sort, $order) {
    $mult = ($order === 'desc') ? -1 : 1;
    
    switch ($sort) {
        case 'title':
            return $mult * strcasecmp($a['title'], $b['title']);
        case 'agency':
            return $mult * strcasecmp($a['agency'], $b['agency']);
        case 'close_date':
            return $mult * (strtotime($a['close_date']) - strtotime($b['close_date']));
        case 'program':
            return $mult * strcasecmp($a['program_name'], $b['program_name']);
        default:
            return 0;
    }
});

// Apply pagination
$total_count = count($all_solicitations);
$solicitations = array_slice($all_solicitations, $offset, $per_page);
$total_pages = ceil($total_count / $per_page);

// Get unique agencies for filter dropdown
$agencies = [];
foreach ($all_solicitations as $sol) {
    if (!empty($sol['agency']) && !in_array($sol['agency'], $agencies)) {
        $agencies[] = $sol['agency'];
    }
}
sort($agencies);

// Page title and meta
$page_title = "Federal Solicitations";
if ($program_filter) {
    $adapter = $programRegistry->getAdapter($program_filter);
    $page_title .= " - " . $adapter->name();
}
$meta_description = "Browse federal solicitations and opportunities. Filter by program, agency, and status. View AI summaries and compliance checklists.";

// Build context for SFBAI
$sfbai_context = [
    'page_type' => 'solicitations_list',
    'total_opportunities' => $total_count,
    'current_filters' => [
        'program' => $program_filter,
        'status' => $status_filter,
        'agency' => $agency_filter,
        'search' => $search
    ],
    'keywords' => ['solicitations', 'opportunities', 'federal', 'contracting', 'rfp', 'rfq']
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
<body class="solicitations-list">
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
    <main id="main-content" class="solicitations-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li>Solicitations</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- File-text icon for solicitations -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10,9 9,9 8,9"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">Federal Solicitations</h1>
                            <p class="page-description">Browse opportunities across federal contract vehicles</p>
                        </div>
                    </div>
                    
                    <div class="page-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($total_count); ?></span>
                            <span class="stat-label">Total Opportunities</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo count(array_filter($all_solicitations, fn($s) => $s['status'] === 'open')); ?></span>
                            <span class="stat-label">Open Now</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo count(array_unique(array_column($all_solicitations, 'agency'))); ?></span>
                            <span class="stat-label">Agencies</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="filters-section">
            <div class="container">
                <form class="filters-form" method="GET" role="search" aria-label="Filter solicitations">
                    <div class="filters-grid">
                        <!-- Search -->
                        <div class="filter-group">
                            <label for="search" class="filter-label">Search</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   class="filter-input" 
                                   placeholder="Search titles, descriptions..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <!-- Program Filter -->
                        <div class="filter-group">
                            <label for="program" class="filter-label">Program</label>
                            <select id="program" name="program" class="filter-select">
                                <option value="">All Programs</option>
                                <?php foreach ($valid_programs as $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $program_filter === $code ? 'selected' : ''; ?>><?php echo strtoupper($code); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label for="status" class="filter-label">Status</label>
                            <select id="status" name="status" class="filter-select">
                                <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <!-- Agency Filter -->
                        <div class="filter-group">
                            <label for="agency" class="filter-label">Agency</label>
                            <select id="agency" name="agency" class="filter-select">
                                <option value="">All Agencies</option>
                                <?php foreach ($agencies as $agency): ?>
                                <option value="<?php echo htmlspecialchars($agency); ?>" 
                                        <?php echo $agency_filter === $agency ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($agency); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Sort Options -->
                        <div class="filter-group">
                            <label for="sort" class="filter-label">Sort By</label>
                            <select id="sort" name="sort" class="filter-select">
                                <option value="close_date" <?php echo $sort === 'close_date' ? 'selected' : ''; ?>>Close Date</option>
                                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                                <option value="agency" <?php echo $sort === 'agency' ? 'selected' : ''; ?>>Agency</option>
                                <option value="program" <?php echo $sort === 'program' ? 'selected' : ''; ?>>Program</option>
                            </select>
                        </div>
                        
                        <!-- Sort Order -->
                        <div class="filter-group">
                            <label for="order" class="filter-label">Order</label>
                            <select id="order" name="order" class="filter-select">
                                <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="/solicitations" class="btn-text">Clear All</a>
                        <button type="button" class="btn-text" id="export-results">Export CSV</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Solicitations Content -->
        <section class="solicitations-content">
            <div class="container">
                <div class="solicitations-grid">
                    <!-- Main Content Area -->
                    <div class="solicitations-main-content">
                        <!-- Results List -->
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <?php echo number_format($total_count); ?> Opportunities Found
                                    <?php if ($program_filter): ?>
                                    <span class="card-subtitle">in <?php echo htmlspecialchars($programRegistry->getAdapter($program_filter)->name()); ?></span>
                                    <?php endif; ?>
                                </h2>
                                <div class="card-actions">
                                    <button class="btn-text view-toggle" data-view="list" aria-label="Switch to list view">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="8" y1="6" x2="21" y2="6"/>
                                            <line x1="8" y1="12" x2="21" y2="12"/>
                                            <line x1="8" y1="18" x2="21" y2="18"/>
                                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                                        </svg>
                                    </button>
                                    <button class="btn-text view-toggle active" data-view="cards" aria-label="Switch to card view">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="7" height="7"/>
                                            <rect x="14" y="3" width="7" height="7"/>
                                            <rect x="14" y="14" width="7" height="7"/>
                                            <rect x="3" y="14" width="7" height="7"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (!empty($solicitations)): ?>
                            <!-- Card View -->
                            <div class="solicitations-cards" id="cards-view">
                                <?php foreach ($solicitations as $solicitation): ?>
                                <div class="solicitation-card tilt-card" tabindex="0">
                                    <div class="solicitation-header">
                                        <div class="solicitation-meta-top">
                                            <span class="solicitation-program"><?php echo htmlspecialchars($solicitation['program_name']); ?></span>
                                            <span class="solicitation-number"><?php echo htmlspecialchars($solicitation['opp_no']); ?></span>
                                        </div>
                                        <h3 class="solicitation-title">
                                            <a href="/solicitations/<?php echo urlencode($solicitation['opp_no']); ?>" 
                                               aria-label="View solicitation details: <?php echo htmlspecialchars($solicitation['title']); ?>">
                                                <?php echo htmlspecialchars($solicitation['title']); ?>
                                            </a>
                                        </h3>
                                    </div>
                                    
                                    <div class="solicitation-details">
                                        <div class="solicitation-agency">
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
                                        </div>
                                        
                                        <div class="solicitation-close-date">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"/>
                                                <polyline points="12,6 12,12 16,14"/>
                                            </svg>
                                            Closes: <?php echo date('M j, Y g:i A', strtotime($solicitation['close_date'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="solicitation-footer">
                                        <span class="status-badge status-<?php echo strtolower($solicitation['status']); ?>">
                                            <?php echo htmlspecialchars($solicitation['status']); ?>
                                        </span>
                                        
                                        <div class="solicitation-actions">
                                            <a href="/solicitations/<?php echo urlencode($solicitation['opp_no']); ?>" 
                                               class="btn-text" 
                                               aria-label="View <?php echo htmlspecialchars($solicitation['title']); ?> details">
                                                View Details
                                            </a>
                                            <a href="<?php echo htmlspecialchars($solicitation['url']); ?>" 
                                               target="_blank" 
                                               rel="noopener" 
                                               class="btn-text"
                                               aria-label="View original solicitation on external site">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                                    <polyline points="15,3 21,3 21,9"/>
                                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                                </svg>
                                                Original
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- List View (Hidden by default) -->
                            <div class="solicitations-table" id="list-view" style="display: none;">
                                <table class="data-table" role="table" aria-label="Solicitations table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Title & Number</th>
                                            <th scope="col">Program</th>
                                            <th scope="col">Agency</th>
                                            <th scope="col">Close Date</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($solicitations as $solicitation): ?>
                                        <tr class="tilt-card" tabindex="0">
                                            <td>
                                                <div class="solicitation-info">
                                                    <span class="solicitation-title-table">
                                                        <a href="/solicitations/<?php echo urlencode($solicitation['opp_no']); ?>">
                                                            <?php echo htmlspecialchars($solicitation['title']); ?>
                                                        </a>
                                                    </span>
                                                    <span class="solicitation-number-table"><?php echo htmlspecialchars($solicitation['opp_no']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($solicitation['program_name']); ?></td>
                                            <td><?php echo htmlspecialchars($solicitation['agency']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($solicitation['close_date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($solicitation['status']); ?>">
                                                    <?php echo htmlspecialchars($solicitation['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="/solicitations/<?php echo urlencode($solicitation['opp_no']); ?>" class="btn-text">View</a>
                                                    <a href="<?php echo htmlspecialchars($solicitation['url']); ?>" target="_blank" rel="noopener" class="btn-text">Original</a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php else: ?>
                            <div class="no-data-message">
                                <p>No solicitations found matching your criteria.</p>
                                <a href="/solicitations" class="btn-text">Clear filters</a>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <nav aria-label="Pagination navigation" role="navigation">
                                    <ul class="pagination-list">
                                        <?php if ($page > 1): ?>
                                        <li>
                                            <a href="<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                               class="pagination-link" 
                                               aria-label="Go to previous page">
                                                Previous
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                               class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>"
                                               aria-label="Go to page <?php echo $i; ?>"
                                               <?php echo $i === $page ? 'aria-current="page"' : ''; ?>>
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                        <li>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
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
                    </div>

                    <!-- SFBAI Sidebar -->
                    <aside class="solicitations-sidebar" role="complementary" aria-label="SFBAI Assistant">
                        <div class="sfbai-panel glassmorphism">
                            <div class="sfbai-header">
                                <h2 class="sfbai-title">SFBAI Assistant</h2>
                                <p class="sfbai-context">Solicitations: <?php echo number_format($total_count); ?> opportunities</p>
                            </div>
                            
                            <div class="sfbai-chatbox">
                                <div id="chat-response" class="chat-response" role="log" aria-live="polite" aria-label="Chat conversation history"></div>
                                
                                <div class="chat-input-container">
                                    <div class="chat-input-wrapper">
                                        <input type="text" 
                                               id="chat-input" 
                                               class="chat-input" 
                                               placeholder="Ask about opportunities..." 
                                               aria-label="Chat with SFBAI assistant about solicitations"
                                               aria-describedby="chat-help-text">
                                        <button class="chat-send" aria-label="Send message">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="22" y1="2" x2="11" y2="13"/>
                                                <polygon points="22,2 15,22 11,13 2,9"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="chat-help-text" class="chat-help-text">
                                        Try: "/opps closing soon" or "Show TLS opportunities"
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
    <script src="/js/solicitations-list.js"></script>
</body>
</html>
