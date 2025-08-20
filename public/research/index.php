<?php
/**
 * Research Documents Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /research/
 * Displays research documents with Google Drive sync, preview, and AI summarization
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
$doc_type_filter = $_GET['doc_type'] ?? '';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'modified';
$order = $_GET['order'] ?? 'desc';

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Valid program codes (display form)
$valid_programs = array_map(function($code) {
    return \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
}, $programRegistry->getAvailablePrograms());
if ($program_filter && !in_array($program_filter, $valid_programs, true)) {
    $program_filter = '';
}

// Build SQL query for research documents
$sql = "
    SELECT rd.*, 
           COUNT(n.id) as notes_count,
           MAX(n.created_at) as last_note_date
    FROM research_docs rd
    LEFT JOIN notes n ON JSON_CONTAINS(n.tags, JSON_QUOTE(CONCAT('doc:', rd.id)))
    WHERE 1=1
";

$params = [];

// Apply filters
if ($program_filter) {
    $sql .= " AND JSON_CONTAINS(rd.tags, JSON_QUOTE(?))";
    $params[] = $program_filter;
}

if ($doc_type_filter) {
    $sql .= " AND rd.doc_type = ?";
    $params[] = $doc_type_filter;
}

if ($search) {
    $sql .= " AND (rd.title LIKE ? OR rd.description LIKE ? OR rd.content LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " GROUP BY rd.id";

// Apply sorting
switch ($sort) {
    case 'title':
        $sql .= " ORDER BY rd.title " . ($order === 'desc' ? 'DESC' : 'ASC');
        break;
    case 'doc_type':
        $sql .= " ORDER BY rd.doc_type " . ($order === 'desc' ? 'DESC' : 'ASC');
        break;
    case 'created':
        $sql .= " ORDER BY rd.created_at " . ($order === 'desc' ? 'DESC' : 'ASC');
        break;
    case 'notes':
        $sql .= " ORDER BY notes_count " . ($order === 'desc' ? 'DESC' : 'ASC');
        break;
    case 'modified':
    default:
        $sql .= " ORDER BY rd.updated_at " . ($order === 'desc' ? 'DESC' : 'ASC');
        break;
}

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$research_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT rd.id) FROM research_docs rd WHERE 1=1";
$countParams = [];

if ($program_filter) {
    $countSql .= " AND JSON_CONTAINS(rd.tags, JSON_QUOTE(?))";
    $countParams[] = $program_filter;
}

if ($doc_type_filter) {
    $countSql .= " AND rd.doc_type = ?";
    $countParams[] = $doc_type_filter;
}

if ($search) {
    $countSql .= " AND (rd.title LIKE ? OR rd.description LIKE ? OR rd.content LIKE ?)";
    $searchTerm = "%{$search}%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total_count = $countStmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get unique document types for filter
$stmt = $pdo->prepare("SELECT DISTINCT doc_type FROM research_docs ORDER BY doc_type");
$stmt->execute();
$doc_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get Drive sync status
$stmt = $pdo->prepare("
    SELECT last_sync, sync_status, documents_synced, errors 
    FROM drive_sync_log 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute();
$sync_status = $stmt->fetch(PDO::FETCH_ASSOC);

// Page title and meta
$page_title = "Research Documents";
if ($program_filter) {
    $adapter = $programRegistry->getAdapter($program_filter);
    if ($adapter) {
        $page_title .= " - " . $adapter->name();
    }
}
$meta_description = "Research documents and knowledge base for federal BD intelligence. View documents, AI summaries, and tagged notes.";

// Build context for SFBAI
$sfbai_context = [
    'page_type' => 'research_docs',
    'total_documents' => $total_count,
    'current_filters' => [
        'program' => $program_filter,
        'doc_type' => $doc_type_filter,
        'search' => $search
    ],
    'keywords' => ['research', 'documents', 'knowledge', 'analysis', 'intelligence']
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
<body class="research-docs">
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
                <a href="/research" class="nav-link active">Research</a>
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content" class="research-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li>Research</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- Book-2 icon for research -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">Research Documents</h1>
                            <p class="page-description">Knowledge base and document intelligence</p>
                        </div>
                    </div>
                    
                    <div class="page-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($total_count); ?></span>
                            <span class="stat-label">Documents</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo count($doc_types); ?></span>
                            <span class="stat-label">Types</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $sync_status ? date('M j', strtotime($sync_status['last_sync'])) : 'Never'; ?></span>
                            <span class="stat-label">Last Sync</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sync Status Banner -->
        <?php if ($sync_status): ?>
        <section class="sync-status-banner">
            <div class="container">
                <div class="sync-status <?php echo strtolower($sync_status['sync_status']); ?>">
                    <div class="sync-status-icon">
                        <?php if ($sync_status['sync_status'] === 'success'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20,6 9,17 4,12"/>
                        </svg>
                        <?php elseif ($sync_status['sync_status'] === 'error'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 1v6m0 6v6"/>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div class="sync-status-content">
                        <span class="sync-status-text">
                            <?php if ($sync_status['sync_status'] === 'success'): ?>
                            Drive sync successful - <?php echo $sync_status['documents_synced']; ?> documents synced
                            <?php elseif ($sync_status['sync_status'] === 'error'): ?>
                            Drive sync failed - <?php echo $sync_status['errors']; ?>
                            <?php else: ?>
                            Drive sync in progress...
                            <?php endif; ?>
                        </span>
                        <span class="sync-status-time">
                            <?php echo date('M j, Y g:i A', strtotime($sync_status['last_sync'])); ?>
                        </span>
                    </div>
                    <?php if ($user['role'] !== 'viewer'): ?>
                    <button class="btn-text" id="trigger-sync-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23,4 23,10 17,10"/>
                            <polyline points="1,20 1,14 7,14"/>
                            <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                        </svg>
                        Sync Now
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Filters -->
        <section class="filters-section">
            <div class="container">
                <form class="filters-form" method="GET" role="search" aria-label="Filter research documents">
                    <div class="filters-grid">
                        <!-- Search -->
                        <div class="filter-group">
                            <label for="search" class="filter-label">Search</label>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   class="filter-input" 
                                   placeholder="Search titles, content..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <!-- Program Filter -->
                        <div class="filter-group">
                            <label for="program" class="filter-label">Program</label>
                            <select id="program" name="program" class="filter-select">
                                <option value="">All Programs</option>
                                <?php foreach ($valid_programs as $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $program_filter === $code ? 'selected' : ''; ?>>
                                    <?php echo strtoupper($code); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Document Type Filter -->
                        <div class="filter-group">
                            <label for="doc_type" class="filter-label">Document Type</label>
                            <select id="doc_type" name="doc_type" class="filter-select">
                                <option value="">All Types</option>
                                <?php foreach ($doc_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                        <?php echo $doc_type_filter === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Sort Options -->
                        <div class="filter-group">
                            <label for="sort" class="filter-label">Sort By</label>
                            <select id="sort" name="sort" class="filter-select">
                                <option value="modified" <?php echo $sort === 'modified' ? 'selected' : ''; ?>>Last Modified</option>
                                <option value="created" <?php echo $sort === 'created' ? 'selected' : ''; ?>>Created Date</option>
                                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                                <option value="doc_type" <?php echo $sort === 'doc_type' ? 'selected' : ''; ?>>Document Type</option>
                                <option value="notes" <?php echo $sort === 'notes' ? 'selected' : ''; ?>>Notes Count</option>
                            </select>
                        </div>
                        
                        <!-- Sort Order -->
                        <div class="filter-group">
                            <label for="order" class="filter-label">Order</label>
                            <select id="order" name="order" class="filter-select">
                                <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="/research" class="btn-text">Clear All</a>
                        <?php if ($user['role'] !== 'viewer'): ?>
                        <button type="button" class="btn-text" id="bulk-summarize-btn">AI Summarize Selected</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <!-- Research Content -->
        <section class="research-content">
            <div class="container">
                <div class="research-grid">
                    <!-- Main Content Area -->
                    <div class="research-main-content">
                        <!-- Results List -->
                        <div class="content-card glassmorphism reveal">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <?php echo number_format($total_count); ?> Documents Found
                                    <?php if ($program_filter): ?>
                                    <span class="card-subtitle">in <?php echo htmlspecialchars($program_filter); ?></span>
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
                            
                            <?php if (!empty($research_docs)): ?>
                            <!-- Card View -->
                            <div class="research-cards" id="cards-view">
                                <?php foreach ($research_docs as $doc): ?>
                                <div class="research-card tilt-card" tabindex="0" data-doc-id="<?php echo $doc['id']; ?>">
                                    <div class="research-card-header">
                                        <div class="research-card-meta">
                                            <span class="research-doc-type"><?php echo htmlspecialchars($doc['doc_type']); ?></span>
                                            <?php if ($user['role'] !== 'viewer'): ?>
                                            <input type="checkbox" 
                                                   class="research-select-checkbox" 
                                                   data-doc-id="<?php echo $doc['id']; ?>"
                                                   aria-label="Select <?php echo htmlspecialchars($doc['title']); ?>">
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="research-card-title">
                                            <a href="<?php echo htmlspecialchars($doc['source_url']); ?>" 
                                               target="_blank" 
                                               rel="noopener"
                                               aria-label="View document: <?php echo htmlspecialchars($doc['title']); ?>">
                                                <?php echo htmlspecialchars($doc['title']); ?>
                                            </a>
                                        </h3>
                                    </div>
                                    
                                    <?php if (!empty($doc['description'])): ?>
                                    <div class="research-card-description">
                                        <?php echo htmlspecialchars(substr($doc['description'], 0, 200)) . (strlen($doc['description']) > 200 ? '...' : ''); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="research-card-details">
                                        <div class="research-card-tags">
                                            <?php 
                                            $tags = json_decode($doc['tags'], true) ?: [];
                                            foreach (array_slice($tags, 0, 3) as $tag): 
                                            ?>
                                            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="research-card-stats">
                                            <?php if ($doc['notes_count'] > 0): ?>
                                            <span class="research-notes-count">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                                    <polyline points="14,2 14,8 20,8"/>
                                                </svg>
                                                <?php echo $doc['notes_count']; ?> notes
                                            </span>
                                            <?php endif; ?>
                                            
                                            <span class="research-modified-date">
                                                <?php echo date('M j, Y', strtotime($doc['updated_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="research-card-actions">
                                        <a href="<?php echo htmlspecialchars($doc['source_url']); ?>" 
                                           target="_blank" 
                                           rel="noopener" 
                                           class="btn-text">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                                <polyline points="15,3 21,3 21,9"/>
                                                <line x1="10" y1="14" x2="21" y2="3"/>
                                            </svg>
                                            View
                                        </a>
                                        
                                        <?php if ($user['role'] !== 'viewer'): ?>
                                        <button class="btn-text ai-summarize-btn" 
                                                data-doc-id="<?php echo $doc['id']; ?>"
                                                aria-label="Generate AI summary for <?php echo htmlspecialchars($doc['title']); ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 20h9"/>
                                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                            </svg>
                                            AI Summary
                                        </button>
                                        
                                        <button class="btn-text view-notes-btn" 
                                                data-doc-id="<?php echo $doc['id']; ?>"
                                                aria-label="View notes for <?php echo htmlspecialchars($doc['title']); ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                                <polyline points="14,2 14,8 20,8"/>
                                                <line x1="16" y1="13" x2="8" y2="13"/>
                                                <line x1="16" y1="17" x2="8" y2="17"/>
                                                <polyline points="10,9 9,9 8,9"/>
                                            </svg>
                                            Notes (<?php echo $doc['notes_count']; ?>)
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php else: ?>
                            <div class="no-data-message">
                                <p>No research documents found matching your criteria.</p>
                                <a href="/research" class="btn-text">Clear filters</a>
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
                    <aside class="research-sidebar" role="complementary" aria-label="SFBAI Assistant">
                        <div class="sfbai-panel glassmorphism">
                            <div class="sfbai-header">
                                <h2 class="sfbai-title">SFBAI Assistant</h2>
                                <p class="sfbai-context">Research: <?php echo number_format($total_count); ?> documents</p>
                            </div>
                            
                            <div class="sfbai-chatbox">
                                <div id="chat-response" class="chat-response" role="log" aria-live="polite" aria-label="Chat conversation history"></div>
                                
                                <div class="chat-input-container">
                                    <div class="chat-input-wrapper">
                                        <input type="text" 
                                               id="chat-input" 
                                               class="chat-input" 
                                               placeholder="Ask about research..." 
                                               aria-label="Chat with SFBAI assistant about research documents"
                                               aria-describedby="chat-help-text">
                                        <button class="chat-send" aria-label="Send message">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="22" y1="2" x2="11" y2="13"/>
                                                <polygon points="22,2 15,22 11,13 2,9"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="chat-help-text" class="chat-help-text">
                                        Try: "Summarize TLS documents" or "Find cyber security research"
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
    <script src="/js/research-docs.js"></script>
</body>
</html>
