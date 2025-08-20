<?php
/**
 * OASIS+ Capability Sheet
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /programs/oasis+/capability-sheet.php?holder_id={id}
 * Generates capability sheets for OASIS+ contract holders
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Adapters\OASISPlusAdapter;

// Initialize managers
$authManager = new AuthManager($pdo);
$oasisAdapter = new OASISPlusAdapter($pdo);

// Check authentication
if (!$authManager->isAuthenticated()) {
    header('Location: /auth/login.php');
    exit;
}

$user = $authManager->getCurrentUser();

// Get holder ID from URL
$holder_id = $_GET['holder_id'] ?? '';
if (empty($holder_id)) {
    header('Location: /programs/oasis+');
    exit;
}

// Get holder data
$holder = $oasisAdapter->getHolderById($holder_id);
if (!$holder) {
    header('Location: /programs/oasis+');
    exit;
}

// Generate capability sheet data
$capability_sheet = $oasisAdapter->generateCapabilitySheet($holder_id);
if (!$capability_sheet) {
    header('Location: /programs/oasis+');
    exit;
}

// Page metadata
$page_title = $holder['name'] . " - OASIS+ Capability Sheet";
$meta_description = "OASIS+ capability sheet for {$holder['name']} showing domains, pool, capabilities, and past performance.";

// Get pools and domains for display
$pools = $oasisAdapter->getPools();
$domains = $oasisAdapter->getDomains();
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
<body class="capability-sheet oasis-plus">
    <!-- Skip Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Navigation (hidden in print) -->
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
                <a href="/programs/oasis+" class="nav-link active">OASIS+</a>
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
    <main id="main-content" class="capability-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li><a href="/programs/oasis+">OASIS+</a></li>
                        <li><a href="/programs/oasis+/holders/<?php echo $holder_id; ?>"><?php echo htmlspecialchars($holder['name']); ?></a></li>
                        <li>Capability Sheet</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- Layout-grid icon for OASIS+ -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="14" y="14" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">OASIS+ Capability Sheet</h1>
                            <p class="page-description"><?php echo htmlspecialchars($holder['name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="page-actions">
                        <button class="btn-secondary" onclick="window.print()" aria-label="Print capability sheet">
                            <!-- Print icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6,9 6,2 18,2 18,9"/>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            Print
                        </button>
                        <a href="/outreach/compose?program=oasis%2B&holder=<?php echo $holder_id; ?>" class="btn-primary">
                            <!-- Send icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22,2 15,22 11,13 2,9"/>
                            </svg>
                            Draft Email
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Capability Sheet Content -->
        <section class="capability-content">
            <div class="container">
                <div class="capability-sheet-container">
                    <!-- Company Overview -->
                    <div class="capability-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Company Overview</h2>
                            <div class="section-meta">
                                <span class="contract-number">Contract: <?php echo htmlspecialchars($holder['contract_number']); ?></span>
                            </div>
                        </div>
                        
                        <div class="company-details">
                            <div class="company-info">
                                <h3 class="company-name"><?php echo htmlspecialchars($holder['full_name']); ?></h3>
                                <p class="company-short-name"><?php echo htmlspecialchars($holder['name']); ?></p>
                            </div>
                            
                            <div class="contract-details">
                                <div class="contract-item">
                                    <span class="contract-label">Pool:</span>
                                    <span class="contract-value">
                                        <?php 
                                        $pool_info = $pools[$holder['pool']] ?? null;
                                        echo htmlspecialchars($pool_info ? $pool_info['name'] . ' (' . $holder['pool'] . ')' : $holder['pool']);
                                        ?>
                                    </span>
                                </div>
                                
                                <?php if ($pool_info): ?>
                                <div class="contract-item">
                                    <span class="contract-label">Pool Ceiling:</span>
                                    <span class="contract-value"><?php echo htmlspecialchars($pool_info['ceiling']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="contract-item">
                                    <span class="contract-label">Contract Number:</span>
                                    <span class="contract-value">
                                        <code><?php echo htmlspecialchars($holder['contract_number']); ?></code>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Domains -->
                    <div class="capability-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Service Domains</h2>
                            <div class="section-meta">
                                <span class="domains-count"><?php echo count($holder['domains']); ?> of 6 domains</span>
                            </div>
                        </div>
                        
                        <div class="domains-grid">
                            <?php foreach ($holder['domains'] as $domain_num): ?>
                            <?php $domain_info = $domains[$domain_num] ?? null; ?>
                            <?php if ($domain_info): ?>
                            <div class="domain-card">
                                <div class="domain-header">
                                    <span class="domain-number">Domain <?php echo $domain_num; ?></span>
                                    <span class="domain-naics">NAICS: <?php echo htmlspecialchars($domain_info['naics_primary']); ?></span>
                                </div>
                                <h3 class="domain-name"><?php echo htmlspecialchars($domain_info['name']); ?></h3>
                                <p class="domain-description"><?php echo htmlspecialchars($domain_info['description']); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Core Capabilities -->
                    <div class="capability-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Core Capabilities</h2>
                            <div class="section-meta">
                                <span class="capabilities-count"><?php echo count($holder['capabilities']); ?> capabilities</span>
                            </div>
                        </div>
                        
                        <div class="capabilities-grid">
                            <?php foreach ($holder['capabilities'] as $capability): ?>
                            <div class="capability-card">
                                <h3 class="capability-name"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $capability))); ?></h3>
                                <div class="capability-tags">
                                    <span class="capability-tag"><?php echo htmlspecialchars($capability); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- NAICS Codes -->
                    <div class="capability-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">NAICS Codes</h2>
                            <div class="section-meta">
                                <span class="naics-count"><?php echo count($holder['naics_codes']); ?> codes</span>
                            </div>
                        </div>
                        
                        <div class="naics-list">
                            <?php foreach ($holder['naics_codes'] as $naics): ?>
                            <div class="naics-item">
                                <code class="naics-code"><?php echo htmlspecialchars($naics); ?></code>
                                <span class="naics-description">
                                    <?php
                                    // Map NAICS codes to descriptions
                                    $naics_descriptions = [
                                        '541611' => 'Administrative Management and General Management Consulting Services',
                                        '541512' => 'Computer Systems Design Services',
                                        '541330' => 'Engineering Services',
                                        '541614' => 'Process, Physical Distribution, and Logistics Consulting Services',
                                        '541715' => 'Research and Development in the Physical, Engineering, and Life Sciences',
                                        '561210' => 'Facilities Support Services'
                                    ];
                                    echo htmlspecialchars($naics_descriptions[$naics] ?? 'Professional Services');
                                    ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Past Performance -->
                    <div class="capability-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Past Performance Summary</h2>
                            <div class="section-meta">
                                <span class="performance-rating rating-<?php echo strtolower(str_replace(' ', '-', $holder['past_performance']['rating'] ?? 'good')); ?>">
                                    <?php echo htmlspecialchars($holder['past_performance']['rating'] ?? 'Good'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="performance-details">
                            <div class="performance-item">
                                <span class="performance-label">Scope of Work:</span>
                                <span class="performance-value"><?php echo htmlspecialchars($holder['past_performance']['scope'] ?? 'Various professional services'); ?></span>
                            </div>
                            
                            <div class="performance-item">
                                <span class="performance-label">Contract Value:</span>
                                <span class="performance-value"><?php echo htmlspecialchars($holder['past_performance']['value'] ?? 'Multiple contracts'); ?></span>
                            </div>
                            
                            <div class="performance-item">
                                <span class="performance-label">Performance Rating:</span>
                                <span class="performance-value">
                                    <span class="rating-badge rating-<?php echo strtolower(str_replace(' ', '-', $holder['past_performance']['rating'] ?? 'good')); ?>">
                                        <?php echo htmlspecialchars($holder['past_performance']['rating'] ?? 'Good'); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Key Differentiators -->
                    <div class="capability-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Key Differentiators</h2>
                        </div>
                        
                        <div class="differentiators-list">
                            <div class="differentiator-item">
                                <h3 class="differentiator-title">Pool Qualification</h3>
                                <p class="differentiator-description">
                                    Qualified for OASIS+ <?php echo htmlspecialchars($pools[$holder['pool']]['name'] ?? $holder['pool']); ?> pool, 
                                    enabling access to <?php echo htmlspecialchars($pools[$holder['pool']]['ceiling'] ?? 'significant contract opportunities'); ?> in opportunities.
                                </p>
                            </div>
                            
                            <div class="differentiator-item">
                                <h3 class="differentiator-title">Multi-Domain Coverage</h3>
                                <p class="differentiator-description">
                                    Covers <?php echo count($holder['domains']); ?> service domains, providing comprehensive capabilities 
                                    across <?php echo implode(', ', array_map(function($d) use ($domains) { return $domains[$d]['name']; }, $holder['domains'])); ?>.
                                </p>
                            </div>
                            
                            <div class="differentiator-item">
                                <h3 class="differentiator-title">Proven Track Record</h3>
                                <p class="differentiator-description">
                                    <?php echo htmlspecialchars($holder['past_performance']['rating'] ?? 'Good'); ?> past performance rating 
                                    with experience in <?php echo htmlspecialchars($holder['past_performance']['scope'] ?? 'federal professional services'); ?>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Print Footer (visible only in print) -->
    <footer class="print-footer">
        <div class="print-footer-content">
            <div class="print-meta">
                <p><strong>Generated:</strong> <?php echo date('F j, Y \a\t g:i A T'); ?></p>
                <p><strong>Source:</strong> samfedbiz.com Federal BD Platform</p>
            </div>
            <div class="print-branding">
                <p><strong>Owner:</strong> Quartermasters FZC</p>
                <p>All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.</p>
            </div>
        </div>
    </footer>

    <!-- Regular Footer (hidden in print) -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 samfedbiz.com | Owner: Quartermasters FZC | All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script>
        // Initialize animations and functionality
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