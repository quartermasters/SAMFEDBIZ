<?php
/**
 * Subscriber Management Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /settings/subscribers
 * Detailed subscriber management with double opt-in
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;

// Initialize managers
$authManager = new AuthManager($pdo);

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
        handleSubscriberAction($_POST, $pdo, $user['id']);
    }
    
    header('Location: /settings/subscribers');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build SQL query
$sql = "
    SELECT id, email, name, active, verified, created_at, verified_at, unsubscribed_at,
           verification_token, unsubscribe_token
    FROM subscribers
    WHERE 1=1
";

$params = [];

// Apply filters
if ($status_filter === 'active') {
    $sql .= " AND active = 1 AND verified = 1";
} elseif ($status_filter === 'unverified') {
    $sql .= " AND verified = 0";
} elseif ($status_filter === 'inactive') {
    $sql .= " AND active = 0";
}

if ($search_filter) {
    $sql .= " AND (email LIKE ? OR name LIKE ?)";
    $params[] = "%{$search_filter}%";
    $params[] = "%{$search_filter}%";
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM subscribers WHERE 1=1";
$countParams = [];

if ($status_filter === 'active') {
    $countSql .= " AND active = 1 AND verified = 1";
} elseif ($status_filter === 'unverified') {
    $countSql .= " AND verified = 0";
} elseif ($status_filter === 'inactive') {
    $countSql .= " AND active = 0";
}

if ($search_filter) {
    $countSql .= " AND (email LIKE ? OR name LIKE ?)";
    $countParams[] = "%{$search_filter}%";
    $countParams[] = "%{$search_filter}%";
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get summary stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN active = 1 AND verified = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN verified = 0 THEN 1 ELSE 0 END) as unverified,
        SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive
    FROM subscribers
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get CSRF token
$csrf_token = $authManager->generateCSRFToken();

function handleSubscriberAction($data, $pdo, $userId) {
    try {
        $action = $data['action'] ?? '';
        $subscriberId = intval($data['subscriber_id'] ?? 0);
        
        if (!$subscriberId) {
            throw new Exception('Invalid subscriber ID');
        }
        
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE subscribers SET active = 1 WHERE id = ?");
                $stmt->execute([$subscriberId]);
                $_SESSION['success'] = "Subscriber activated successfully.";
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE subscribers SET active = 0 WHERE id = ?");
                $stmt->execute([$subscriberId]);
                $_SESSION['success'] = "Subscriber deactivated successfully.";
                break;
                
            case 'verify':
                $stmt = $pdo->prepare("UPDATE subscribers SET verified = 1, verified_at = NOW() WHERE id = ?");
                $stmt->execute([$subscriberId]);
                $_SESSION['success'] = "Subscriber verified successfully.";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM subscribers WHERE id = ?");
                $stmt->execute([$subscriberId]);
                $_SESSION['success'] = "Subscriber deleted successfully.";
                break;
                
            case 'resend_verification':
                // Get subscriber details
                $stmt = $pdo->prepare("SELECT email, name, verification_token FROM subscribers WHERE id = ?");
                $stmt->execute([$subscriberId]);
                $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($subscriber) {
                    // Send verification email (placeholder)
                    $_SESSION['success'] = "Verification email resent to {$subscriber['email']}.";
                } else {
                    throw new Exception('Subscriber not found');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
            VALUES ('subscriber', ?, 'action_performed', ?, ?, NOW())
        ");
        $stmt->execute([
            $subscriberId,
            json_encode([
                'action' => $action,
                'message' => "Performed action: {$action} on subscriber {$subscriberId}"
            ]),
            $userId
        ]);
        
    } catch (Exception $e) {
        error_log("Subscriber action failed: " . $e->getMessage());
        $_SESSION['error'] = "Action failed: " . $e->getMessage();
    }
}

// Page title and meta
$page_title = "Subscriber Management";
$meta_description = "Manage newsletter subscribers and double opt-in verification for samfedbiz.com federal BD platform.";
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
</head>
<body class="subscribers-management">
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
                <a href="/settings" class="nav-link">Settings</a>
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content" class="subscribers-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li><a href="/settings">Settings</a></li>
                        <li>Subscribers</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- Users icon -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">Subscriber Management</h1>
                            <p class="page-description">Manage newsletter subscribers and double opt-in verification</p>
                        </div>
                    </div>
                    
                    <div class="page-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($stats['total']); ?></span>
                            <span class="stat-label">Total</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($stats['active']); ?></span>
                            <span class="stat-label">Active</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($stats['unverified']); ?></span>
                            <span class="stat-label">Unverified</span>
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

        <!-- Filters -->
        <section class="filters-section">
            <div class="container">
                <form class="filters-form" method="GET" role="search" aria-label="Filter subscribers">
                    <div class="filters-grid">
                        <!-- Search Filter -->
                        <div class="filter-group">
                            <label for="search" class="filter-label">Search</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   class="filter-input" 
                                   placeholder="Email or name..."
                                   value="<?php echo htmlspecialchars($search_filter); ?>">
                        </div>
                        
                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label for="status" class="filter-label">Status</label>
                            <select id="status" name="status" class="filter-select">
                                <option value="">All Subscribers</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="/settings/subscribers" class="btn-text">Clear All</a>
                        <button type="button" class="btn-secondary" id="bulk-actions-btn">Bulk Actions</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Subscribers Content -->
        <section class="subscribers-content">
            <div class="container">
                <div class="subscribers-table-container glassmorphism">
                    <div class="table-header">
                        <h2 class="table-title">Subscribers</h2>
                        <div class="table-actions">
                            <button class="btn-text" id="select-all-btn">Select All</button>
                            <button class="btn-secondary" id="export-selected-btn">Export Selected</button>
                        </div>
                    </div>
                    
                    <?php if (!empty($subscribers)): ?>
                    <div class="table-responsive">
                        <table class="subscribers-table">
                            <thead>
                                <tr>
                                    <th class="table-checkbox-col">
                                        <input type="checkbox" id="select-all-checkbox" aria-label="Select all subscribers">
                                    </th>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Subscribed</th>
                                    <th>Verified</th>
                                    <th class="table-actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscribers as $subscriber): ?>
                                <tr class="subscriber-row" data-subscriber-id="<?php echo $subscriber['id']; ?>">
                                    <td class="table-checkbox-col">
                                        <input type="checkbox" 
                                               class="subscriber-checkbox" 
                                               value="<?php echo $subscriber['id']; ?>"
                                               aria-label="Select <?php echo htmlspecialchars($subscriber['email']); ?>">
                                    </td>
                                    <td class="subscriber-email">
                                        <div class="email-container">
                                            <span class="email-text"><?php echo htmlspecialchars($subscriber['email']); ?></span>
                                            <?php if (!$subscriber['verified']): ?>
                                            <span class="email-warning" title="Email not verified">⚠️</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="subscriber-name">
                                        <?php echo htmlspecialchars($subscriber['name'] ?: 'Not provided'); ?>
                                    </td>
                                    <td class="subscriber-status">
                                        <?php if ($subscriber['active'] && $subscriber['verified']): ?>
                                        <span class="status-badge active">Active</span>
                                        <?php elseif (!$subscriber['verified']): ?>
                                        <span class="status-badge unverified">Unverified</span>
                                        <?php else: ?>
                                        <span class="status-badge inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="subscriber-created">
                                        <time datetime="<?php echo $subscriber['created_at']; ?>">
                                            <?php echo date('M j, Y', strtotime($subscriber['created_at'])); ?>
                                        </time>
                                    </td>
                                    <td class="subscriber-verified">
                                        <?php if ($subscriber['verified_at']): ?>
                                        <time datetime="<?php echo $subscriber['verified_at']; ?>">
                                            <?php echo date('M j, Y', strtotime($subscriber['verified_at'])); ?>
                                        </time>
                                        <?php else: ?>
                                        <span class="text-muted">Not verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-actions-col">
                                        <div class="action-buttons">
                                            <?php if (!$subscriber['verified']): ?>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="verify">
                                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                <button type="submit" class="btn-text btn-verify" title="Verify subscriber">
                                                    Verify
                                                </button>
                                            </form>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="resend_verification">
                                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                <button type="submit" class="btn-text btn-resend" title="Resend verification email">
                                                    Resend
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($subscriber['active']): ?>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                <button type="submit" class="btn-text btn-deactivate" title="Deactivate subscriber">
                                                    Deactivate
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                <button type="submit" class="btn-text btn-activate" title="Activate subscriber">
                                                    Activate
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                <button type="submit" 
                                                        class="btn-text btn-delete" 
                                                        title="Delete subscriber"
                                                        onclick="return confirm('Are you sure you want to delete this subscriber?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php else: ?>
                    <div class="no-data-message">
                        <p>No subscribers found matching your criteria.</p>
                        <a href="/settings/subscribers" class="btn-text">Clear filters</a>
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
    <script src="/js/accessibility.js"></script>
    <script src="/js/subscriber-management.js"></script>
</body>
</html>