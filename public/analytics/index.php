<?php
/**
 * Analytics Dashboard
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Displays engagement, conversion, content, and reliability metrics
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Core\ProgramRegistry;

// Initialize managers
$authManager = new AuthManager($pdo);
$programRegistry = new ProgramRegistry($pdo);

// Check authentication
if (!$authManager->isAuthenticated()) {
    header('Location: /auth/login.php');
    exit;
}

$user = $authManager->getCurrentUser();

// Check admin/ops permissions for analytics
if (!in_array($user['role'], ['admin', 'ops'])) {
    http_response_code(403);
    echo "Access denied. Analytics requires admin or ops role.";
    exit;
}

// Date range from query params (default: last 30 days)
$start_date = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end'] ?? date('Y-m-d');
$program_filter_raw = $_GET['program'] ?? '';
$program_filter_norm = \SamFedBiz\Core\ProgramRegistry::normalizeCode($program_filter_raw);
$program_filter = $program_filter_raw === '' ? '' : \SamFedBiz\Core\ProgramRegistry::getDisplayCode($program_filter_norm);

// Get available programs
$available_programs = [];
foreach ($programRegistry->getAvailablePrograms() as $code) {
    $display = \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
    $adapter = $programRegistry->getAdapter($code);
    if ($adapter) {
        $available_programs[$display] = $adapter->name();
    }
}

// Build analytics data
$analytics_data = [];

try {
    // Engagement metrics
    $engagement_query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_interactions,
            COUNT(DISTINCT user_id) as unique_users,
            AVG(LENGTH(message)) as avg_message_length
        FROM chat_messages 
        WHERE created_at BETWEEN ? AND ?
    ";
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $engagement_query .= " AND JSON_EXTRACT(context, '$.program') = ?";
        $params[] = $program_filter;
    }
    
    $engagement_query .= " GROUP BY DATE(created_at) ORDER BY date";
    
    $stmt = $pdo->prepare($engagement_query);
    $stmt->execute($params);
    $analytics_data['engagement'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conversion metrics
    $conversion_query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_outreach,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful_sends,
            COUNT(CASE WHEN response_received = 1 THEN 1 END) as responses_received
        FROM outreach 
        WHERE created_at BETWEEN ? AND ?
    ";
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $conversion_query .= " AND program_code = ?";
        $params[] = $program_filter;
    }
    
    $conversion_query .= " GROUP BY DATE(created_at) ORDER BY date";
    
    $stmt = $pdo->prepare($conversion_query);
    $stmt->execute($params);
    $analytics_data['conversion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Content metrics  
    $content_query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as briefs_sent,
            AVG(JSON_LENGTH(sections)) as avg_sections_per_brief,
            COUNT(CASE WHEN JSON_EXTRACT(metrics, '$.open_rate') > 0 THEN 1 END) as opened_briefs
        FROM daily_briefs 
        WHERE created_at BETWEEN ? AND ? AND status = 'sent'
        GROUP BY DATE(created_at) 
        ORDER BY date
    ";
    
    $stmt = $pdo->prepare($content_query);
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $analytics_data['content'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reliability metrics
    $reliability_query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_news_items,
            COUNT(CASE WHEN reliability_score >= 0.8 THEN 1 END) as confirmed_items,
            COUNT(CASE WHEN reliability_score BETWEEN 0.5 AND 0.79 THEN 1 END) as developing_items,
            COUNT(CASE WHEN reliability_score < 0.5 THEN 1 END) as signal_items,
            AVG(reliability_score) as avg_reliability
        FROM news_items 
        WHERE created_at BETWEEN ? AND ?
    ";
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $reliability_query .= " AND program_code = ?";
        $params[] = $program_filter;
    }
    
    $reliability_query .= " GROUP BY DATE(created_at) ORDER BY date";
    
    $stmt = $pdo->prepare($reliability_query);
    $stmt->execute($params);
    $analytics_data['reliability'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary metrics
    $summary_metrics = [
        'total_users' => 0,
        'total_interactions' => 0,
        'total_outreach' => 0,
        'avg_response_rate' => 0,
        'total_briefs' => 0,
        'avg_reliability' => 0
    ];
    
    // Calculate summary metrics
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) as total_users FROM users WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $summary_metrics['total_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_interactions FROM chat_messages WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $summary_metrics['total_interactions'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_outreach,
            COALESCE(AVG(CASE WHEN response_received = 1 THEN 1 ELSE 0 END) * 100, 0) as avg_response_rate
        FROM outreach 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $outreach_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary_metrics['total_outreach'] = $outreach_data['total_outreach'];
    $summary_metrics['avg_response_rate'] = round($outreach_data['avg_response_rate'], 1);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_briefs FROM daily_briefs WHERE created_at BETWEEN ? AND ? AND status = 'sent'");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $summary_metrics['total_briefs'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(reliability_score), 0) as avg_reliability FROM news_items WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $summary_metrics['avg_reliability'] = round($stmt->fetchColumn(), 2);
    
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    $analytics_data = ['engagement' => [], 'conversion' => [], 'content' => [], 'reliability' => []];
    $summary_metrics = ['total_users' => 0, 'total_interactions' => 0, 'total_outreach' => 0, 'avg_response_rate' => 0, 'total_briefs' => 0, 'avg_reliability' => 0];
}

// Page metadata
$page_title = "Analytics Dashboard";
$meta_description = "Federal BD platform analytics including engagement, conversion, content, and reliability metrics.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <title><?php echo htmlspecialchars($page_title); ?> | samfedbiz.com</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/styles/main.css">
    <link rel="stylesheet" href="/styles/analytics.css">
    <link rel="stylesheet" href="/styles/accessibility.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://unpkg.com/gsap@3.12.2/dist/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.27/dist/lenis.min.js"></script>
</head>
<body class="analytics-dashboard">
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
                <a href="/analytics" class="nav-link active">Analytics</a>
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Announcement region for screen readers -->
    <div class="announcement-region" role="status" aria-live="polite" aria-label="Status updates" id="status-announcements"></div>

    <!-- Main Content -->
    <main id="main-content" class="analytics-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li>Analytics</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- Bar-chart icon for Analytics -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="20" x2="12" y2="10"/>
                                <line x1="18" y1="20" x2="18" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="16"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">Analytics Dashboard</h1>
                            <p class="page-description">Platform metrics and performance insights</p>
                        </div>
                    </div>
                    
                    <div class="page-actions">
                        <a href="/analytics/export?start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>&program=<?php echo $program_filter; ?>" class="btn-secondary">
                            <!-- Download icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7,10 12,15 17,10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Export CSV
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Analytics Filters -->
        <section class="analytics-filters">
            <div class="container">
                <form class="filters-form glassmorphism" method="GET">
                    <div class="filter-group">
                        <label for="start-date" class="filter-label">Start Date</label>
                        <input type="date" id="start-date" name="start" value="<?php echo htmlspecialchars($start_date); ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end-date" class="filter-label">End Date</label>
                        <input type="date" id="end-date" name="end" value="<?php echo htmlspecialchars($end_date); ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="program-filter" class="filter-label">Program</label>
                        <select id="program-filter" name="program" class="filter-select">
                            <option value="">All Programs</option>
                            <?php foreach ($available_programs as $code => $name): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $program_filter === $code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <!-- Filter icon -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"/>
                        </svg>
                        Apply Filters
                    </button>
                </form>
            </div>
        </section>

        <!-- Summary Metrics -->
        <section class="summary-metrics">
            <div class="container">
                <div class="metrics-grid">
                    <div class="metric-card glassmorphism reveal">
                        <div class="metric-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <h3 class="metric-title">Total Users</h3>
                            <p class="metric-value"><?php echo number_format($summary_metrics['total_users']); ?></p>
                        </div>
                    </div>
                    
                    <div class="metric-card glassmorphism reveal">
                        <div class="metric-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <h3 class="metric-title">Chat Interactions</h3>
                            <p class="metric-value"><?php echo number_format($summary_metrics['total_interactions']); ?></p>
                        </div>
                    </div>
                    
                    <div class="metric-card glassmorphism reveal">
                        <div class="metric-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22,2 15,22 11,13 2,9"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <h3 class="metric-title">Outreach Sent</h3>
                            <p class="metric-value"><?php echo number_format($summary_metrics['total_outreach']); ?></p>
                        </div>
                    </div>
                    
                    <div class="metric-card glassmorphism reveal">
                        <div class="metric-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <h3 class="metric-title">Response Rate</h3>
                            <p class="metric-value"><?php echo $summary_metrics['avg_response_rate']; ?>%</p>
                        </div>
                    </div>
                    
                    <div class="metric-card glassmorphism reveal">
                        <div class="metric-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <h3 class="metric-title">Briefs Sent</h3>
                            <p class="metric-value"><?php echo number_format($summary_metrics['total_briefs']); ?></p>
                        </div>
                    </div>
                    
                    <div class="metric-card glassmorphism reveal">
                        <div class="metric-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
                                <polyline points="9,12 11,14 16,9"/>
                            </svg>
                        </div>
                        <div class="metric-content">
                            <h3 class="metric-title">Avg Reliability</h3>
                            <p class="metric-value"><?php echo $summary_metrics['avg_reliability']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="analytics-charts">
            <div class="container">
                <div class="charts-grid">
                    <!-- Engagement Chart -->
                    <div class="chart-card glassmorphism reveal">
                        <div class="chart-header">
                            <h2 class="chart-title">User Engagement</h2>
                            <p class="chart-description">Daily interactions and unique users</p>
                        </div>
                        <div class="chart-container">
                            <canvas id="engagement-chart" 
                                    role="img" 
                                    aria-label="Line chart showing daily user engagement metrics including total interactions and unique users over time"
                                    tabindex="0"></canvas>
                            <div class="chart-description sr-only">
                                Chart displays engagement trends with total interactions and unique users plotted over the selected date range.
                            </div>
                        </div>
                    </div>

                    <!-- Conversion Chart -->
                    <div class="chart-card glassmorphism reveal">
                        <div class="chart-header">
                            <h2 class="chart-title">Outreach Conversion</h2>
                            <p class="chart-description">Outreach sent vs responses received</p>
                        </div>
                        <div class="chart-container">
                            <canvas id="conversion-chart" 
                                    role="img" 
                                    aria-label="Bar chart showing outreach conversion metrics including sent emails, successful deliveries, and response rates"
                                    tabindex="0"></canvas>
                            <div class="chart-description sr-only">
                                Chart shows conversion funnel from total outreach attempts to successful sends to responses received.
                            </div>
                        </div>
                    </div>

                    <!-- Content Chart -->
                    <div class="chart-card glassmorphism reveal">
                        <div class="chart-header">
                            <h2 class="chart-title">Content Performance</h2>
                            <p class="chart-description">Brief delivery and engagement</p>
                        </div>
                        <div class="chart-container">
                            <canvas id="content-chart" 
                                    role="img" 
                                    aria-label="Line chart showing content performance metrics including briefs sent and open rates over time"
                                    tabindex="0"></canvas>
                            <div class="chart-description sr-only">
                                Chart tracks daily brief sending and engagement showing briefs sent versus briefs opened.
                            </div>
                        </div>
                    </div>

                    <!-- Reliability Chart -->
                    <div class="chart-card glassmorphism reveal">
                        <div class="chart-header">
                            <h2 class="chart-title">Information Reliability</h2>
                            <p class="chart-description">News source reliability distribution</p>
                        </div>
                        <div class="chart-container">
                            <canvas id="reliability-chart" 
                                    role="img" 
                                    aria-label="Stacked bar chart showing information reliability distribution including confirmed, developing, and signal level news items"
                                    tabindex="0"></canvas>
                            <div class="chart-description sr-only">
                                Chart shows daily breakdown of news items by reliability score: confirmed (80%+), developing (50-79%), and signals (below 50%).
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
    <script>
        // Pass analytics data to JavaScript
        window.analyticsData = <?php echo json_encode($analytics_data); ?>;
    </script>
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/accessibility-core.js"></script>
    <script src="/js/analytics-charts.js"></script>
</body>
</html>
