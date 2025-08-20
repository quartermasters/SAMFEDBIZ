<?php
/**
 * CSV Template Downloads
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Provides CSV template downloads for import functionality
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;

// Initialize managers
$authManager = new AuthManager($pdo);

// Check authentication and admin role
if (!$authManager->isAuthenticated()) {
    header('Location: /auth/login.php');
    exit;
}

$user = $authManager->getCurrentUser();

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied. Template downloads require admin role.";
    exit;
}

// Handle template download
$template_type = $_GET['type'] ?? '';

if (!empty($template_type)) {
    downloadTemplate($template_type);
    exit;
}

/**
 * Generate and download CSV template
 */
function downloadTemplate($type) {
    $templates = getTemplateDefinitions();
    
    if (!isset($templates[$type])) {
        http_response_code(404);
        echo "Template not found";
        return;
    }
    
    $template = $templates[$type];
    $filename = "samfedbiz_{$type}_template.csv";
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, $template['headers']);
    
    // Write example rows
    foreach ($template['examples'] as $example) {
        fputcsv($output, $example);
    }
    
    fclose($output);
}

/**
 * Get template definitions
 */
function getTemplateDefinitions() {
    return [
        'holders' => [
            'headers' => [
                'name',
                'full_name',
                'location',
                'status',
                'capabilities',
                'contract_number',
                'sewp_group',
                'pool',
                'domains',
                'naics_codes',
                'psc_codes',
                'oem_authorizations'
            ],
            'examples' => [
                [
                    'Example Corp',
                    'Example Corporation LLC',
                    'Washington, DC',
                    'active',
                    'software_development|cloud_services|cybersecurity',
                    'NNG15SC01B',
                    'B',
                    'SB',
                    '1|2|3',
                    '541511|541512|541519',
                    '7030|7040|R425',
                    'Microsoft|Oracle|VMware'
                ],
                [
                    'Tech Solutions Inc',
                    'Technology Solutions Incorporated',
                    'Reston, VA',
                    'active',
                    'hardware|networking|storage',
                    'NNG15SC02B',
                    'A',
                    'UR',
                    '2|4|5',
                    '334111|423430',
                    '7021|7025|5820',
                    'Cisco|Dell|HP'
                ]
            ]
        ],
        
        'opportunities' => [
            'headers' => [
                'opp_no',
                'title',
                'agency',
                'description',
                'status',
                'type',
                'close_date',
                'url'
            ],
            'examples' => [
                [
                    'W52P1J-25-R-0001',
                    'Enterprise IT Infrastructure Modernization',
                    'Department of Defense',
                    'Comprehensive modernization of enterprise IT infrastructure including cloud migration and cybersecurity enhancements.',
                    'open',
                    'RFP',
                    '2025-03-15 17:00:00',
                    'https://sam.gov/opp/example1'
                ],
                [
                    'GS-35F-0119Y-25-0002',
                    'Software Licensing and Support Services',
                    'General Services Administration',
                    'Multi-year software licensing agreement with implementation and ongoing support services.',
                    'open',
                    'RFQ',
                    '2025-02-28 15:00:00',
                    'https://sam.gov/opp/example2'
                ]
            ]
        ],
        
        'research_docs' => [
            'headers' => [
                'title',
                'doc_type',
                'content',
                'source_url',
                'tags'
            ],
            'examples' => [
                [
                    'FY25 IT Modernization Strategy',
                    'strategy_document',
                    'Executive summary of the government-wide IT modernization strategy for fiscal year 2025.',
                    'https://cio.gov/example-doc1.pdf',
                    'tls|cybersecurity|modernization|strategy'
                ],
                [
                    'SEWP VI Contract Modifications Overview',
                    'contract_update',
                    'Summary of recent modifications to SEWP VI contracts affecting procurement procedures.',
                    'https://sewp.nasa.gov/example-doc2.pdf',
                    'sewp|contracts|procurement|updates'
                ]
            ]
        ],
        
        'subscribers' => [
            'headers' => [
                'email',
                'name',
                'status',
                'programs'
            ],
            'examples' => [
                [
                    'john.doe@example.com',
                    'John Doe',
                    'confirmed',
                    'tls|oasis+|sewp'
                ],
                [
                    'jane.smith@contractor.gov',
                    'Jane Smith',
                    'confirmed',
                    'sewp|oasis+'
                ]
            ]
        ]
    ];
}

// Page metadata
$page_title = "CSV Import Templates";
$meta_description = "Download CSV templates for bulk data import.";
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
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://unpkg.com/gsap@3.12.2/dist/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.27/dist/lenis.min.js"></script>
</head>
<body class="templates-page">
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
                <a href="/analytics" class="nav-link">Analytics</a>
                <a href="/settings" class="nav-link active">Settings</a>
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content" class="templates-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li><a href="/settings">Settings</a></li>
                        <li><a href="/admin/import">CSV Import</a></li>
                        <li>Templates</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- File-spreadsheet icon -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10,9 9,9 8,9"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">CSV Import Templates</h1>
                            <p class="page-description">Download template files for bulk data import</p>
                        </div>
                    </div>
                    
                    <div class="page-actions">
                        <a href="/admin/import" class="btn-secondary">
                            <!-- Arrow-left icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12"/>
                                <polyline points="12,19 5,12 12,5"/>
                            </svg>
                            Back to Import
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Templates Grid -->
        <section class="templates-grid-section">
            <div class="container">
                <div class="templates-grid">
                    <!-- Holders Template -->
                    <div class="template-card glassmorphism reveal">
                        <div class="template-header">
                            <div class="template-icon">
                                <!-- Building icon -->
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
                                    <path d="M6 12H4a2 2 0 0 0-2 2v8h4"/>
                                    <path d="M18 9h2a2 2 0 0 1 2 2v11h-4"/>
                                    <path d="M10 6h4"/>
                                    <path d="M10 10h4"/>
                                    <path d="M10 14h4"/>
                                    <path d="M10 18h4"/>
                                </svg>
                            </div>
                            <h2 class="template-title">Contract Holders</h2>
                        </div>
                        
                        <div class="template-content">
                            <p class="template-description">
                                Import contract holders and prime contractors with program-specific metadata including capabilities, contract numbers, and certifications.
                            </p>
                            
                            <div class="template-fields">
                                <h3 class="fields-title">Required Fields:</h3>
                                <ul class="fields-list">
                                    <li>name</li>
                                    <li>full_name</li>
                                </ul>
                                
                                <h3 class="fields-title">Optional Fields:</h3>
                                <ul class="fields-list">
                                    <li>location, status, capabilities</li>
                                    <li>contract_number, sewp_group, pool</li>
                                    <li>domains, naics_codes, psc_codes</li>
                                    <li>oem_authorizations</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="template-actions">
                            <a href="?type=holders" class="btn-primary">
                                <!-- Download icon -->
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7,10 12,15 17,10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Download Template
                            </a>
                        </div>
                    </div>

                    <!-- Opportunities Template -->
                    <div class="template-card glassmorphism reveal">
                        <div class="template-header">
                            <div class="template-icon">
                                <!-- Target icon -->
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <circle cx="12" cy="12" r="6"/>
                                    <circle cx="12" cy="12" r="2"/>
                                </svg>
                            </div>
                            <h2 class="template-title">Opportunities</h2>
                        </div>
                        
                        <div class="template-content">
                            <p class="template-description">
                                Import solicitations, RFPs, and other procurement opportunities with agency information and important dates.
                            </p>
                            
                            <div class="template-fields">
                                <h3 class="fields-title">Required Fields:</h3>
                                <ul class="fields-list">
                                    <li>opp_no</li>
                                    <li>title</li>
                                    <li>agency</li>
                                </ul>
                                
                                <h3 class="fields-title">Optional Fields:</h3>
                                <ul class="fields-list">
                                    <li>description, status, type</li>
                                    <li>close_date, url</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="template-actions">
                            <a href="?type=opportunities" class="btn-primary">
                                <!-- Download icon -->
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7,10 12,15 17,10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Download Template
                            </a>
                        </div>
                    </div>

                    <!-- Research Documents Template -->
                    <div class="template-card glassmorphism reveal">
                        <div class="template-header">
                            <div class="template-icon">
                                <!-- File-text icon -->
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10,9 9,9 8,9"/>
                                </svg>
                            </div>
                            <h2 class="template-title">Research Documents</h2>
                        </div>
                        
                        <div class="template-content">
                            <p class="template-description">
                                Import research documents, reports, and reference materials with categorization and tagging support.
                            </p>
                            
                            <div class="template-fields">
                                <h3 class="fields-title">Required Fields:</h3>
                                <ul class="fields-list">
                                    <li>title</li>
                                    <li>source_url</li>
                                </ul>
                                
                                <h3 class="fields-title">Optional Fields:</h3>
                                <ul class="fields-list">
                                    <li>doc_type, content</li>
                                    <li>tags (pipe-separated)</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="template-actions">
                            <a href="?type=research_docs" class="btn-primary">
                                <!-- Download icon -->
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7,10 12,15 17,10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Download Template
                            </a>
                        </div>
                    </div>

                    <!-- Subscribers Template -->
                    <div class="template-card glassmorphism reveal">
                        <div class="template-header">
                            <div class="template-icon">
                                <!-- Mail icon -->
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </div>
                            <h2 class="template-title">Newsletter Subscribers</h2>
                        </div>
                        
                        <div class="template-content">
                            <p class="template-description">
                                Import newsletter subscribers with email preferences and program subscriptions for targeted communications.
                            </p>
                            
                            <div class="template-fields">
                                <h3 class="fields-title">Required Fields:</h3>
                                <ul class="fields-list">
                                    <li>email</li>
                                    <li>name</li>
                                </ul>
                                
                                <h3 class="fields-title">Optional Fields:</h3>
                                <ul class="fields-list">
                                    <li>status</li>
                                    <li>programs (pipe-separated)</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="template-actions">
                            <a href="?type=subscribers" class="btn-primary">
                                <!-- Download icon -->
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7,10 12,15 17,10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                Download Template
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Usage Instructions -->
                <div class="usage-instructions glassmorphism reveal">
                    <h2 class="instructions-title">How to Use Templates</h2>
                    <div class="instructions-grid">
                        <div class="instruction-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h3>Download Template</h3>
                                <p>Click the download button for the type of data you want to import. Each template includes headers and example rows.</p>
                            </div>
                        </div>
                        
                        <div class="instruction-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h3>Fill Your Data</h3>
                                <p>Replace the example rows with your actual data. Keep the header row intact and follow the field format guidelines.</p>
                            </div>
                        </div>
                        
                        <div class="instruction-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h3>Save as CSV</h3>
                                <p>Save your file as a CSV format. Ensure pipe-separated values (|) are used for multi-value fields like capabilities and tags.</p>
                            </div>
                        </div>
                        
                        <div class="instruction-step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h3>Upload & Import</h3>
                                <p>Return to the import page and upload your completed CSV file. Review any error messages and fix data as needed.</p>
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
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize animations
            if (typeof gsap !== 'undefined') {
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
            }
        });
    </script>
</body>
</html>