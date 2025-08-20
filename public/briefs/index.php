<?php
/**
 * Daily Briefs Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /briefs/
 * Displays daily brief archive with build/send functionality
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
// Keep 'general' as-is for briefs; otherwise use display code for DB tags
$program_filter = $program_filter_raw === '' || $program_filter_raw === 'general'
    ? $program_filter_raw
    : \SamFedBiz\Core\ProgramRegistry::getDisplayCode($program_filter_norm);
$date_filter = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build SQL query for briefs
$sql = "
    SELECT id, title, content, sections, tags, created_at, sent_at, recipient_count
    FROM daily_briefs
    WHERE 1=1
";

$params = [];

// Apply filters
if ($program_filter) {
    $sql .= " AND JSON_CONTAINS(tags, JSON_QUOTE(?))";
    $params[] = $program_filter;
}

if ($date_filter) {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $date_filter;
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$briefs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM daily_briefs WHERE 1=1";
$countParams = [];

if ($program_filter) {
    $countSql .= " AND JSON_CONTAINS(tags, JSON_QUOTE(?))";
    $countParams[] = $program_filter;
}

if ($date_filter) {
    $countSql .= " AND DATE(created_at) = ?";
    $countParams[] = $date_filter;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get today's brief status
$stmt = $pdo->prepare("
    SELECT id, title, created_at, sent_at, recipient_count
    FROM daily_briefs 
    WHERE DATE(created_at) = CURDATE()
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute();
$today_brief = $stmt->fetch(PDO::FETCH_ASSOC);

// Get next scheduled send time (06:05 Dubai time)
$dubai_tz = new DateTimeZone('Asia/Dubai');
$now_dubai = new DateTime('now', $dubai_tz);
$next_send = new DateTime('tomorrow 06:05', $dubai_tz);
if ($now_dubai->format('H:i') < '06:05') {
    $next_send = new DateTime('today 06:05', $dubai_tz);
}

// Get subscriber count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE active = 1");
$stmt->execute();
$subscriber_count = $stmt->fetchColumn();

// Page title and meta
$page_title = "Daily Briefs";
$meta_description = "Daily intelligence briefs for federal business development. View archive, build custom briefs, and manage distributions.";

// Build context for SFBAI
$sfbai_context = [
    'page_type' => 'daily_briefs',
    'total_briefs' => $total_count,
    'today_brief_status' => $today_brief ? 'sent' : 'pending',
    'subscriber_count' => $subscriber_count,
    'keywords' => ['briefs', 'intelligence', 'news', 'daily', 'updates']
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
<body class="daily-briefs">
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
                <a href="/briefs" class="nav-link active">Briefs</a>
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
    <main id="main-content" class="briefs-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li>Daily Briefs</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- News icon for briefs -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/>
                                <path d="M18 14h-8"/>
                                <path d="M15 18h-5"/>
                                <path d="M10 6h8v4h-8V6Z"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">Daily Briefs</h1>
                            <p class="page-description">Federal BD intelligence delivered daily at 06:05 Dubai time</p>
                        </div>
                    </div>
                    
                    <div class="page-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($total_count); ?></span>
                            <span class="stat-label">Total Briefs</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($subscriber_count); ?></span>
                            <span class="stat-label">Subscribers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $next_send->format('H:i'); ?></span>
                            <span class="stat-label">Next Send</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Today's Brief Status -->
        <section class="today-brief-status">
            <div class="container">
                <div class="brief-status-card glassmorphism">
                    <div class="brief-status-header">
                        <h2 class="brief-status-title">Today's Brief</h2>
                        <span class="brief-status-date"><?php echo date('M j, Y'); ?></span>
                    </div>
                    
                    <div class="brief-status-content">
                        <?php if ($today_brief): ?>
                        <div class="brief-status-info">
                            <div class="brief-status-icon success">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20,6 9,17 4,12"/>
                                </svg>
                            </div>
                            <div class="brief-status-details">
                                <span class="brief-status-text">Brief sent at <?php echo date('g:i A', strtotime($today_brief['sent_at'])); ?></span>
                                <span class="brief-status-recipients"><?php echo number_format($today_brief['recipient_count']); ?> recipients</span>
                            </div>
                        </div>
                        
                        <div class="brief-status-actions">
                            <a href="/briefs/<?php echo $today_brief['id']; ?>" class="btn-text">View Brief</a>
                            <?php if ($user['role'] !== 'viewer'): ?>
                            <button class="btn-text" id="resend-brief-btn" data-brief-id="<?php echo $today_brief['id']; ?>">Resend</button>
                            <?php endif; ?>
                        </div>
                        
                        <?php else: ?>
                        <div class="brief-status-info">
                            <div class="brief-status-icon pending">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12,6 12,12 16,14"/>
                                </svg>
                            </div>
                            <div class="brief-status-details">
                                <span class="brief-status-text">No brief sent today</span>
                                <span class="brief-status-next">Next scheduled: <?php echo $next_send->format('g:i A T'); ?></span>
                            </div>
                        </div>
                        
                        <div class="brief-status-actions">
                            <?php if ($user['role'] !== 'viewer'): ?>
                            <button class="btn-primary" id="build-brief-btn">Build Brief Now</button>
                            <button class="btn-secondary" id="preview-brief-btn">Preview</button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="filters-section">
            <div class="container">
                <form class="filters-form" method="GET" role="search" aria-label="Filter daily briefs">
                    <div class="filters-grid">
                        <!-- Date Filter -->
                        <div class="filter-group">
                            <label for="date" class="filter-label">Date</label>
                            <input type="date" 
                                   id="date" 
                                   name="date" 
                                   class="filter-input" 
                                   value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        
                        <!-- Program Filter -->
                        <div class="filter-group">
                            <label for="program" class="filter-label">Program Focus</label>
                            <select id="program" name="program" class="filter-select">
                                <option value="">All Programs</option>
                                <?php 
                                $valid_programs = array_map(function($code) {
                                    return \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
                                }, $programRegistry->getAvailablePrograms());
                                foreach ($valid_programs as $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $program_filter === $code ? 'selected' : ''; ?>><?php echo strtoupper($code); ?></option>
                                <?php endforeach; ?>
                                <option value="general" <?php echo $program_filter === 'general' ? 'selected' : ''; ?>>General</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="/briefs" class="btn-text">Clear All</a>
                        <?php if ($user['role'] !== 'viewer'): ?>
                        <button type="button" class="btn-text" id="manage-subscribers-btn">Manage Subscribers</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <!-- Intelligence Reliability Legend -->
        <section class="reliability-info">
            <div class="container">
                <div class="reliability-disclaimer glassmorphism">
                    <h3>Intelligence Reliability Guide</h3>
                    <p>All information in daily briefs is categorized by reliability to help you assess credibility:</p>
                    
                    <div class="reliability-legend">
                        <div class="reliability-legend-item confirmed">
                            <span>✅ Confirmed Updates</span>
                            <small>Official announcements from verified government sources</small>
                        </div>
                        <div class="reliability-legend-item developing">
                            <span>🔄 Developing Stories</span>
                            <small>News from credible sources requiring further monitoring</small>
                        </div>
                        <div class="reliability-legend-item signal">
                            <span>📊 Signals & Market Intelligence</span>
                            <small>Unconfirmed reports, rumors, and industry speculation requiring verification</small>
                        </div>
                    </div>
                    
                    <p><strong>Important:</strong> Always verify information marked as signals or developing stories before taking action. Confirmed updates represent official announcements from government sources.</p>
                </div>
            </div>
        </section>

        <!-- Briefs Content -->
        <section class="briefs-content">
            <div class="container">
                <div class="briefs-grid">
                    <!-- Main Content Area -->
                    <div class="briefs-main-content">
                        <!-- Archive List -->
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">Brief Archive</h2>
                                <div class="card-actions">
                                    <?php if ($user['role'] !== 'viewer'): ?>
                                    <button class="btn-text" id="export-archive-btn">Export Archive</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($briefs)): ?>
                            <div class="briefs-list">
                                <?php foreach ($briefs as $brief): ?>
                                <div class="brief-item tilt-card" tabindex="0">
                                    <div class="brief-item-header">
                                        <div class="brief-item-meta">
                                            <span class="brief-date"><?php echo date('M j, Y', strtotime($brief['created_at'])); ?></span>
                                            <span class="brief-time"><?php echo date('g:i A', strtotime($brief['created_at'])); ?></span>
                                        </div>
                                        
                                        <div class="brief-status-indicators">
                                            <?php if ($brief['sent_at']): ?>
                                            <span class="brief-sent-indicator">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="22" y1="2" x2="11" y2="13"/>
                                                    <polygon points="22,2 15,22 11,13 2,9"/>
                                                </svg>
                                                Sent to <?php echo number_format($brief['recipient_count']); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="brief-draft-indicator">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M12 20h9"/>
                                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                                </svg>
                                                Draft
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <h3 class="brief-item-title">
                                        <a href="/briefs/<?php echo $brief['id']; ?>" 
                                           aria-label="View brief: <?php echo htmlspecialchars($brief['title']); ?>">
                                            <?php echo htmlspecialchars($brief['title']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="brief-item-sections">
                                        <?php 
                                        $sections = json_decode($brief['sections'], true) ?: [];
                                        foreach (array_slice($sections, 0, 4) as $section): 
                                        ?>
                                        <span class="brief-section-tag"><?php echo htmlspecialchars($section['title'] ?? $section); ?></span>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($sections) > 4): ?>
                                        <span class="brief-sections-more">+<?php echo count($sections) - 4; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="brief-item-tags">
                                        <?php 
                                        $tags = json_decode($brief['tags'], true) ?: [];
                                        foreach (array_slice($tags, 0, 3) as $tag): 
                                        ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="brief-item-actions">
                                        <a href="/briefs/<?php echo $brief['id']; ?>" class="btn-text">View Full</a>
                                        <?php if ($user['role'] !== 'viewer'): ?>
                                        <button class="btn-text resend-btn" data-brief-id="<?php echo $brief['id']; ?>">Resend</button>
                                        <button class="btn-text duplicate-btn" data-brief-id="<?php echo $brief['id']; ?>">Duplicate</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php else: ?>
                            <div class="no-data-message">
                                <p>No briefs found matching your criteria.</p>
                                <a href="/briefs" class="btn-text">Clear filters</a>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <nav aria-label="Pagination navigation" role="navigation">
                                    <ul class="pagination-list">
                                        <?php if ($page > 1): ?>
                                        <li>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
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
                    <aside class="briefs-sidebar" role="complementary" aria-label="SFBAI Assistant">
                        <div class="sfbai-panel glassmorphism">
                            <div class="sfbai-header">
                                <h2 class="sfbai-title">SFBAI Assistant</h2>
                                <p class="sfbai-context">Daily Briefs: <?php echo number_format($total_count); ?> archived</p>
                            </div>
                            
                            <div class="sfbai-chatbox">
                                <div id="chat-response" class="chat-response" role="log" aria-live="polite" aria-label="Chat conversation history"></div>
                                
                                <div class="chat-input-container">
                                    <div class="chat-input-wrapper">
                                        <input type="text" 
                                               id="chat-input" 
                                               class="chat-input" 
                                               placeholder="Ask about briefs..." 
                                               aria-label="Chat with SFBAI assistant about daily briefs"
                                               aria-describedby="chat-help-text">
                                        <button class="chat-send" aria-label="Send message">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="22" y1="2" x2="11" y2="13"/>
                                                <polygon points="22,2 15,22 11,13 2,9"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="chat-help-text" class="chat-help-text">
                                        Try: "/brief tls" or "Build custom brief"
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
        window.briefsData = {
            next_send_time: <?php echo json_encode($next_send->format('c')); ?>,
            subscriber_count: <?php echo $subscriber_count; ?>,
            today_brief_sent: <?php echo $today_brief ? 'true' : 'false'; ?>
        };
    </script>
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/sfbai-chat.js"></script>
    <script src="/js/daily-briefs.js"></script>
</body>
</html>
