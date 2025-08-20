<?php
/**
 * SEWP Ordering Guide
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /programs/sewp/ordering-guide.php?holder_id={id}
 * Provides ordering guides and marketplace shortcuts for SEWP contract holders
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Adapters\SEWPAdapter;

// Initialize managers
$authManager = new AuthManager($pdo);
$sewpAdapter = new SEWPAdapter($pdo);

// Check authentication
if (!$authManager->isAuthenticated()) {
    header('Location: /auth/login.php');
    exit;
}

$user = $authManager->getCurrentUser();

// Get holder ID from URL (optional)
$holder_id = $_GET['holder_id'] ?? '';
$holder = null;
$ordering_guide = null;

if (!empty($holder_id)) {
    $holder = $sewpAdapter->getHolderById($holder_id);
    if ($holder) {
        $ordering_guide = $sewpAdapter->generateOrderingGuide($holder_id);
    }
}

// Get marketplace shortcuts
$marketplace_shortcuts = $sewpAdapter->getMarketplaceShortcuts();
$groups = $sewpAdapter->getGroups();

// Page metadata
$page_title = $holder ? $holder['name'] . " - SEWP Ordering Guide" : "SEWP Ordering Guide & Marketplace";
$meta_description = $holder ? "SEWP ordering guide for {$holder['name']} with marketplace links and contract information." : "SEWP VI ordering guides and marketplace shortcuts for IT/AV solutions.";
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
<body class="ordering-guide sewp">
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
                <a href="/programs/oasis+" class="nav-link">OASIS+</a>
                <a href="/programs/sewp" class="nav-link active">SEWP</a>
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
    <main id="main-content" class="ordering-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li><a href="/programs/sewp">SEWP</a></li>
                        <?php if ($holder): ?>
                        <li><a href="/programs/sewp/holders/<?php echo $holder_id; ?>"><?php echo htmlspecialchars($holder['name']); ?></a></li>
                        <?php endif; ?>
                        <li>Ordering Guide</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- Device-desktop icon for SEWP -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                <line x1="8" y1="21" x2="16" y2="21"/>
                                <line x1="12" y1="17" x2="12" y2="21"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">SEWP VI Ordering Guide</h1>
                            <p class="page-description">
                                <?php echo $holder ? htmlspecialchars($holder['name']) : 'Marketplace shortcuts and ordering guides'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="page-actions">
                        <button class="btn-secondary" onclick="window.print()" aria-label="Print ordering guide">
                            <!-- Print icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6,9 6,2 18,2 18,9"/>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                <rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            Print
                        </button>
                        <?php if ($holder): ?>
                        <a href="/outreach/compose?program=sewp&holder=<?php echo $holder_id; ?>" class="btn-primary">
                            <!-- Send icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22,2 15,22 11,13 2,9"/>
                            </svg>
                            Draft Email
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Marketplace Shortcuts -->
        <section class="marketplace-shortcuts">
            <div class="container">
                <div class="shortcuts-header">
                    <h2 class="shortcuts-title">Quick Access Marketplace</h2>
                    <p class="shortcuts-description">Direct links to NASA SEWP VI marketplace categories</p>
                </div>
                
                <div class="shortcuts-grid">
                    <?php foreach ($marketplace_shortcuts as $key => $shortcut): ?>
                    <div class="shortcut-card glassmorphism reveal tilt-card" tabindex="0">
                        <div class="shortcut-header">
                            <div class="shortcut-icon">
                                <?php if ($shortcut['group'] === 'A'): ?>
                                    <!-- Hardware icon -->
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                        <line x1="8" y1="21" x2="16" y2="21"/>
                                        <line x1="12" y1="17" x2="12" y2="21"/>
                                    </svg>
                                <?php elseif ($shortcut['group'] === 'B'): ?>
                                    <!-- Software icon -->
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="16,18 22,12 16,6"/>
                                        <polyline points="8,6 2,12 8,18"/>
                                    </svg>
                                <?php else: ?>
                                    <!-- Services icon -->
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2L2 7v10c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V7l-10-5z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="shortcut-meta">
                                <span class="shortcut-group">Group <?php echo htmlspecialchars($shortcut['group']); ?></span>
                            </div>
                        </div>
                        
                        <h3 class="shortcut-name"><?php echo htmlspecialchars($shortcut['name']); ?></h3>
                        <p class="shortcut-description"><?php echo htmlspecialchars($shortcut['description']); ?></p>
                        
                        <div class="shortcut-actions">
                            <a href="<?php echo htmlspecialchars($shortcut['url']); ?>" 
                               target="_blank" 
                               rel="noopener"
                               class="btn-primary"
                               aria-label="Open <?php echo htmlspecialchars($shortcut['name']); ?> marketplace">
                                <!-- External link icon -->
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    <polyline points="15,3 21,3 21,9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                </svg>
                                Visit Marketplace
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php if ($holder && $ordering_guide): ?>
        <!-- Specific Holder Ordering Guide -->
        <section class="ordering-content">
            <div class="container">
                <div class="ordering-guide-container">
                    <!-- Company Overview -->
                    <div class="ordering-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Contract Holder Information</h2>
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
                                    <span class="contract-label">SEWP Group:</span>
                                    <span class="contract-value">
                                        <?php 
                                        $group_info = $groups[$holder['sewp_group']] ?? null;
                                        echo htmlspecialchars($group_info ? $group_info['name'] : 'Group ' . $holder['sewp_group']);
                                        ?>
                                    </span>
                                </div>
                                
                                <?php if ($group_info): ?>
                                <div class="contract-item">
                                    <span class="contract-label">Focus Area:</span>
                                    <span class="contract-value"><?php echo htmlspecialchars($group_info['focus']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="contract-item">
                                    <span class="contract-label">Contract Number:</span>
                                    <span class="contract-value">
                                        <code><?php echo htmlspecialchars($holder['contract_number']); ?></code>
                                    </span>
                                </div>
                                
                                <?php if (!empty($holder['marketplace_url'])): ?>
                                <div class="contract-item">
                                    <span class="contract-label">Marketplace:</span>
                                    <span class="contract-value">
                                        <a href="<?php echo htmlspecialchars($holder['marketplace_url']); ?>" 
                                           target="_blank" 
                                           rel="noopener"
                                           class="marketplace-link">
                                            <?php echo htmlspecialchars($holder['marketplace_url']); ?>
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- OEM Authorizations -->
                    <div class="ordering-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">OEM Authorizations</h2>
                            <div class="section-meta">
                                <span class="oem-count"><?php echo count($holder['oem_authorizations']); ?> authorizations</span>
                            </div>
                        </div>
                        
                        <div class="oem-grid">
                            <?php foreach ($holder['oem_authorizations'] as $oem): ?>
                            <div class="oem-card">
                                <h3 class="oem-name"><?php echo htmlspecialchars($oem); ?></h3>
                                <div class="oem-status">
                                    <span class="status-badge status-active">Authorized</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- NAICS and PSC Codes -->
                    <div class="ordering-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Classification Codes</h2>
                        </div>
                        
                        <div class="codes-grid">
                            <div class="codes-column">
                                <h3 class="codes-title">NAICS Codes</h3>
                                <div class="codes-list">
                                    <?php foreach ($holder['naics_codes'] as $naics): ?>
                                    <div class="code-item">
                                        <code class="code-number"><?php echo htmlspecialchars($naics); ?></code>
                                        <span class="code-description">
                                            <?php
                                            // Map NAICS codes to descriptions
                                            $naics_descriptions = [
                                                '334111' => 'Electronic Computer Manufacturing',
                                                '334210' => 'Telephone Apparatus Manufacturing',
                                                '423430' => 'Computer and Computer Peripheral Equipment and Software Merchant Wholesalers',
                                                '511210' => 'Software Publishers',
                                                '541511' => 'Custom Computer Programming Services',
                                                '541512' => 'Computer Systems Design Services',
                                                '541519' => 'Other Computer Related Services',
                                                '541990' => 'All Other Professional, Scientific, and Technical Services'
                                            ];
                                            echo htmlspecialchars($naics_descriptions[$naics] ?? 'Information Technology');
                                            ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="codes-column">
                                <h3 class="codes-title">PSC Codes</h3>
                                <div class="codes-list">
                                    <?php foreach ($holder['psc_codes'] as $psc): ?>
                                    <div class="code-item">
                                        <code class="code-number"><?php echo htmlspecialchars($psc); ?></code>
                                        <span class="code-description">
                                            <?php
                                            // Map PSC codes to descriptions
                                            $psc_descriptions = [
                                                '5820' => 'Radio and Television Communication Equipment',
                                                '5895' => 'Miscellaneous Communication Equipment',
                                                '7021' => 'Furniture',
                                                '7025' => 'Household and Commercial Furnishings and Appliances',
                                                '7030' => 'Information Technology Software',
                                                '7035' => 'Information Technology Equipment Software',
                                                '7040' => 'Information Technology Software',
                                                'R425' => 'Support Services'
                                            ];
                                            echo htmlspecialchars($psc_descriptions[$psc] ?? 'IT/AV Equipment');
                                            ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Capabilities -->
                    <div class="ordering-section glassmorphism reveal">
                        <div class="section-header">
                            <h2 class="section-title">Product & Service Capabilities</h2>
                        </div>
                        
                        <div class="capabilities-grid">
                            <?php foreach ($holder['capabilities'] as $capability): ?>
                            <div class="capability-card">
                                <h3 class="capability-name"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $capability))); ?></h3>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- SEWP Groups Overview -->
        <section class="groups-overview">
            <div class="container">
                <div class="groups-header">
                    <h2 class="groups-title">SEWP VI Groups Overview</h2>
                    <p class="groups-description">Understanding the three SEWP VI procurement groups</p>
                </div>
                
                <div class="groups-grid">
                    <?php foreach ($groups as $group_code => $group): ?>
                    <div class="group-card glassmorphism reveal">
                        <div class="group-header">
                            <span class="group-code">Group <?php echo htmlspecialchars($group_code); ?></span>
                            <span class="group-focus"><?php echo htmlspecialchars($group['focus']); ?></span>
                        </div>
                        
                        <h3 class="group-name"><?php echo htmlspecialchars($group['name']); ?></h3>
                        <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                    </div>
                    <?php endforeach; ?>
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
            
            // Tilt cards animation
            const tiltCards = document.querySelectorAll('.tilt-card');
            tiltCards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const rotateX = (y - centerY) / 10;
                    const rotateY = (centerX - x) / 10;
                    
                    card.style.transform = `perspective(1000px) rotateX(${Math.max(-6, Math.min(6, rotateX))}deg) rotateY(${Math.max(-6, Math.min(6, rotateY))}deg)`;
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
                });
            });
        });
    </script>
</body>
</html>