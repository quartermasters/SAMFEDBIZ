<?php
/**
 * TLS Micro-Catalog Builder
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /programs/tls/micro-catalog/{prime_id?}
 * Builds and displays micro-catalogs for TLS prime contractors
 */

require_once __DIR__ . '/../../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Adapters\TLSAdapter;
use SamFedBiz\Config\EnvManager;

// Initialize managers
$envManager = new EnvManager();
$authManager = new AuthManager($pdo);
$tlsAdapter = new TLSAdapter();

// Check authentication
if (!$authManager->isAuthenticated()) {
    header('Location: /auth/login.php');
    exit;
}

$user = $authManager->getCurrentUser();

// Parse URL to get prime ID if provided
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathSegments = explode('/', trim($path, '/'));
$prime_id = $pathSegments[3] ?? null;

// Get all TLS primes for selection
$primes = $tlsAdapter->listPrimesOrHolders();
$selected_prime = null;
$catalog_data = null;

if ($prime_id) {
    $selected_prime = $tlsAdapter->getPrimeById($prime_id);
    if ($selected_prime) {
        $catalog_data = $tlsAdapter->generateMicroCatalog($prime_id);
    }
}

// Handle catalog generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        http_response_code(403);
        die('CSRF validation failed');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_catalog') {
        $requested_prime_id = $_POST['prime_id'] ?? '';
        if ($requested_prime_id) {
            header("Location: /programs/tls/micro-catalog/{$requested_prime_id}");
            exit;
        }
    }
    
    if ($action === 'generate_pdf' && $prime_id) {
        // Generate PDF export - placeholder for future implementation
        $_SESSION['info'] = "PDF generation feature coming soon.";
    }
}

// Page metadata
$page_title = $selected_prime ? "Micro-Catalog: {$selected_prime['name']}" : "TLS Micro-Catalog Builder";
$meta_description = "Build and customize micro-catalogs for TLS prime contractors with part numbers, capabilities, and BOM support.";

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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
    <link rel="stylesheet" href="/styles/print.css" media="print">
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://unpkg.com/gsap@3.12.2/dist/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.27/dist/lenis.min.js"></script>
</head>
<body class="micro-catalog-builder">
    <!-- Skip Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Navigation -->
    <nav class="nav-main no-print" role="navigation" aria-label="Main navigation">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/" class="nav-brand-link" aria-label="samfedbiz.com home">
                    <span class="nav-brand-text">samfedbiz</span>
                </a>
            </div>
            
            <div class="nav-links">
                <a href="/" class="nav-link">Dashboard</a>
                <a href="/programs/tls" class="nav-link active">TLS</a>
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
    <main id="main-content" class="catalog-main">
        <!-- Page Header -->
        <section class="page-header no-print">
            <div class="header-container">
                <div class="header-content glassmorphism reveal">
                    <div class="header-text">
                        <h1 class="header-title">
                            <!-- TLS Shield Icon -->
                            <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <?php echo htmlspecialchars($page_title); ?>
                        </h1>
                        <p class="header-description"><?php echo htmlspecialchars($meta_description); ?></p>
                    </div>
                    
                    <div class="header-actions">
                        <div class="breadcrumb">
                            <a href="/programs/tls" class="breadcrumb-link">TLS Program</a>
                            <span class="breadcrumb-separator">→</span>
                            <span class="breadcrumb-current">Micro-Catalog</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Prime Selection -->
        <?php if (!$selected_prime): ?>
        <section class="prime-selection no-print">
            <div class="content-container">
                <div class="selection-card glassmorphism reveal">
                    <h2 class="selection-title">Select Prime Contractor</h2>
                    <p class="selection-description">Choose a TLS prime contractor to generate their micro-catalog</p>
                    
                    <form method="POST" class="prime-selection-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="generate_catalog">
                        
                        <div class="prime-grid">
                            <?php foreach ($primes as $prime): ?>
                            <div class="prime-card">
                                <div class="prime-header">
                                    <h3 class="prime-name"><?php echo htmlspecialchars($prime['name']); ?></h3>
                                    <p class="prime-full-name"><?php echo htmlspecialchars($prime['full_name']); ?></p>
                                </div>
                                
                                <div class="prime-details">
                                    <div class="prime-detail-item">
                                        <span class="detail-label">Contract:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($prime['contract_number']); ?></span>
                                    </div>
                                    <div class="prime-detail-item">
                                        <span class="detail-label">Lead Time:</span>
                                        <span class="detail-value"><?php echo $prime['lead_time_days']; ?> days</span>
                                    </div>
                                    <div class="prime-detail-item">
                                        <span class="detail-label">Kit Support:</span>
                                        <span class="detail-value <?php echo $prime['kit_support'] ? 'text-success' : 'text-muted'; ?>">
                                            <?php echo $prime['kit_support'] ? 'Available' : 'Not Available'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="prime-capabilities">
                                    <h4 class="capabilities-title">Capabilities</h4>
                                    <div class="capability-tags">
                                        <?php foreach ($prime['capabilities'] as $capability): ?>
                                        <span class="capability-tag"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $capability))); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" name="prime_id" value="<?php echo htmlspecialchars($prime['id']); ?>" 
                                        class="btn-primary prime-select-btn">
                                    Generate Catalog
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Generated Catalog -->
        <?php if ($selected_prime && $catalog_data): ?>
        <section class="catalog-content">
            <div class="content-container">
                <!-- Catalog Actions (No Print) -->
                <div class="catalog-actions glassmorphism reveal no-print">
                    <div class="actions-left">
                        <a href="/programs/tls/micro-catalog" class="btn-secondary">
                            ← Back to Selection
                        </a>
                    </div>
                    <div class="actions-right">
                        <button onclick="window.print()" class="btn-secondary">
                            <!-- Print Icon -->
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="6,9 6,2 18,2 18,9"/>
                                <path d="M6,18H4a2,2 0,0,1-2-2V11a2,2 0,0,1,2-2H20a2,2 0,0,1,2,2v5a2,2 0,0,1-2,2H18"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            Print Catalog
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="generate_pdf">
                            <button type="submit" class="btn-primary">
                                <!-- Download Icon -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7,10 12,15 17,10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Export PDF
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Catalog Header -->
                <div class="catalog-header">
                    <div class="catalog-title-section">
                        <h1 class="catalog-title"><?php echo htmlspecialchars($selected_prime['full_name']); ?></h1>
                        <p class="catalog-subtitle">Tactical Logistics Support (TLS) Micro-Catalog</p>
                        <div class="catalog-meta">
                            <span class="meta-item">Contract: <?php echo htmlspecialchars($selected_prime['contract_number']); ?></span>
                            <span class="meta-separator">•</span>
                            <span class="meta-item">Generated: <?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>
                    
                    <div class="catalog-contact">
                        <h3 class="contact-title">Contact Information</h3>
                        <div class="contact-details">
                            <div class="contact-item">
                                <span class="contact-label">Email:</span>
                                <span class="contact-value"><?php echo htmlspecialchars($catalog_data['contact_info']['email']); ?></span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">Phone:</span>
                                <span class="contact-value"><?php echo htmlspecialchars($catalog_data['contact_info']['phone']); ?></span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-label">Website:</span>
                                <span class="contact-value"><?php echo htmlspecialchars($catalog_data['contact_info']['website']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Capabilities Overview -->
                <div class="catalog-section">
                    <h2 class="section-title">Capabilities Overview</h2>
                    <div class="capabilities-grid">
                        <?php foreach ($catalog_data['catalog_sections'] as $section): ?>
                        <div class="capability-card">
                            <h3 class="capability-title"><?php echo htmlspecialchars($section['title']); ?></h3>
                            <p class="capability-description"><?php echo htmlspecialchars($section['description']); ?></p>
                            
                            <div class="capability-details">
                                <div class="detail-group">
                                    <h4 class="detail-title">Example Parts</h4>
                                    <div class="part-list">
                                        <?php foreach ($section['example_parts'] as $part): ?>
                                        <span class="part-number"><?php echo htmlspecialchars($part); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="detail-group">
                                    <h4 class="detail-title">Use Cases</h4>
                                    <ul class="use-case-list">
                                        <?php foreach ($section['use_cases'] as $useCase): ?>
                                        <li class="use-case-item"><?php echo htmlspecialchars($useCase); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Kit Support & BOM -->
                <?php if ($selected_prime['kit_support']): ?>
                <div class="catalog-section">
                    <h2 class="section-title">Kit Support & Bill of Materials (BOM)</h2>
                    <div class="kit-support-info">
                        <div class="kit-feature">
                            <h3 class="feature-title">Custom Kit Assembly</h3>
                            <p class="feature-description">
                                <?php echo htmlspecialchars($selected_prime['name']); ?> provides custom kit assembly services
                                with comprehensive Bill of Materials (BOM) documentation for all tactical equipment packages.
                            </p>
                        </div>
                        
                        <div class="bom-example">
                            <h4 class="bom-title">Sample BOM Structure</h4>
                            <table class="bom-table">
                                <thead>
                                    <tr>
                                        <th>Item #</th>
                                        <th>Part Number</th>
                                        <th>Description</th>
                                        <th>Qty</th>
                                        <th>Category</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>001</td>
                                        <td>TG-001-KIT</td>
                                        <td>Tactical Gear Assembly Kit</td>
                                        <td>1</td>
                                        <td>Primary</td>
                                    </tr>
                                    <tr>
                                        <td>002</td>
                                        <td>PE-100-STD</td>
                                        <td>Standard Protective Equipment</td>
                                        <td>2</td>
                                        <td>Safety</td>
                                    </tr>
                                    <tr>
                                        <td>003</td>
                                        <td>ACC-050</td>
                                        <td>Accessory Package</td>
                                        <td>1</td>
                                        <td>Support</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Lead Time & Delivery -->
                <div class="catalog-section">
                    <h2 class="section-title">Lead Time & Delivery Information</h2>
                    <div class="delivery-info">
                        <div class="delivery-metric">
                            <span class="metric-value"><?php echo $selected_prime['lead_time_days']; ?></span>
                            <span class="metric-label">Days Standard Lead Time</span>
                        </div>
                        <div class="delivery-details">
                            <h4 class="details-title">Delivery Capabilities</h4>
                            <ul class="delivery-list">
                                <li>Standard delivery: <?php echo $selected_prime['lead_time_days']; ?> business days</li>
                                <li>Expedited delivery available upon request</li>
                                <li>CONUS and OCONUS shipping supported</li>
                                <li>Government billing and invoicing</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Footer Branding -->
                <div class="catalog-footer">
                    <div class="footer-content">
                        <p class="footer-branding">
                            All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.
                        </p>
                        <p class="footer-generated">
                            Generated by samfedbiz.com on <?php echo date('F j, Y \a\t g:i A'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Scripts -->
    <script src="/js/micro-catalog.js"></script>
    <script>
        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            // GSAP reveal animations
            gsap.registerPlugin(ScrollTrigger);
            
            gsap.from('.reveal', {
                y: 30,
                opacity: 0,
                duration: 0.6,
                stagger: 0.08,
                scrollTrigger: {
                    trigger: '.reveal',
                    start: 'top 85%',
                    toggleActions: 'play none none reverse'
                }
            });
            
            // Initialize Lenis smooth scroll
            const lenis = new Lenis({
                duration: 1.0,
                smoothWheel: true
            });
            
            function raf(time) {
                lenis.raf(time);
                requestAnimationFrame(raf);
            }
            requestAnimationFrame(raf);
        });
    </script>
</body>
</html>