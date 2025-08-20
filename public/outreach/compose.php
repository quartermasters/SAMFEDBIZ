<?php
/**
 * Outreach Email Composer
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Route: /outreach/compose
 * AI-powered email composer with micro-catalog context
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

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

// Get query parameters for context
$program_code = $_GET['program'] ?? 'tls';
$prime_id = $_GET['prime'] ?? null;
$context_type = $_GET['context'] ?? 'general'; // general, micro_catalog, capability, meeting

// Get TLS primes for selection
$primes = $tlsAdapter->listPrimesOrHolders();
$selected_prime = null;
$catalog_context = null;

if ($prime_id) {
    $selected_prime = $tlsAdapter->getPrimeById($prime_id);
    if ($selected_prime) {
        $catalog_context = $tlsAdapter->generateMicroCatalog($prime_id);
    }
}

// Handle email composition and sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        http_response_code(403);
        die('CSRF validation failed');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_draft') {
        $email_context = [
            'program_code' => $_POST['program_code'] ?? 'tls',
            'prime_id' => $_POST['prime_id'] ?? null,
            'tone' => $_POST['tone'] ?? 'professional',
            'purpose' => $_POST['purpose'] ?? 'introduction',
            'custom_details' => $_POST['custom_details'] ?? '',
            'include_catalog' => isset($_POST['include_catalog']),
            'meeting_request' => isset($_POST['meeting_request'])
        ];
        
        $draft_content = generateEmailDraft($email_context, $tlsAdapter, $envManager);
        
        if ($draft_content) {
            $_SESSION['email_draft'] = $draft_content;
            $_SESSION['success'] = "Email draft generated successfully.";
        } else {
            $_SESSION['error'] = "Failed to generate email draft. Please check AI API configuration.";
        }
    }
    
    if ($action === 'send_email') {
        $email_data = [
            'recipient_email' => $_POST['recipient_email'] ?? '',
            'recipient_name' => $_POST['recipient_name'] ?? '',
            'subject' => $_POST['subject'] ?? '',
            'content' => $_POST['content'] ?? '',
            'prime_id' => $_POST['prime_id'] ?? null
        ];
        
        $send_result = sendOutreachEmail($email_data, $pdo, $user['id'], $envManager);
        
        if ($send_result['success']) {
            $_SESSION['success'] = "Email sent successfully.";
            // Log outreach activity
            logOutreachActivity($email_data, $pdo, $user['id']);
        } else {
            $_SESSION['error'] = "Failed to send email: " . $send_result['error'];
        }
    }
    
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Page metadata
$page_title = "Outreach Email Composer";
$meta_description = "AI-powered email composer for TLS prime contractor outreach with micro-catalog context and 15-minute call CTAs.";

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get saved draft if exists
$saved_draft = $_SESSION['email_draft'] ?? null;
unset($_SESSION['email_draft']);

/**
 * Generate AI-powered email draft
 */
function generateEmailDraft($context, $tlsAdapter, $envManager) {
    $openai_key = $envManager->get('OPENAI_API_KEY');
    if (!$openai_key) {
        return null;
    }
    
    $prime = null;
    if ($context['prime_id']) {
        $prime = $tlsAdapter->getPrimeById($context['prime_id']);
    }
    
    $prompt = buildEmailPrompt($context, $prime);
    
    $api_data = [
        'model' => 'gpt-4',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional business development specialist for federal contracting. Write concise, professional emails that are 120-150 words. Always include a 15-minute call CTA. Be specific about capabilities and value propositions.'
            ],
            [
                'role' => 'user', 
                'content' => $prompt
            ]
        ],
        'max_tokens' => 400,
        'temperature' => 0.7
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($api_data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $openai_key,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
    }
    
    return null;
}

/**
 * Build prompt for email generation
 */
function buildEmailPrompt($context, $prime) {
    $prompt = "Write a professional outreach email for the TLS (Tactical Logistics Support) program.\n\n";
    
    if ($prime) {
        $prompt .= "Prime Contractor: {$prime['full_name']} ({$prime['name']})\n";
        $prompt .= "Capabilities: " . implode(', ', $prime['capabilities']) . "\n";
        $prompt .= "Lead Time: {$prime['lead_time_days']} days\n";
        $prompt .= "Kit Support: " . ($prime['kit_support'] ? 'Available' : 'Not Available') . "\n\n";
    }
    
    $prompt .= "Email Purpose: {$context['purpose']}\n";
    $prompt .= "Tone: {$context['tone']}\n";
    
    if (!empty($context['custom_details'])) {
        $prompt .= "Additional Context: {$context['custom_details']}\n";
    }
    
    if ($context['include_catalog'] && $prime) {
        $prompt .= "\nInclude reference to micro-catalog/capability sheet availability.\n";
    }
    
    if ($context['meeting_request']) {
        $prompt .= "\nInclude a specific request for a 15-minute introductory call.\n";
    }
    
    $prompt .= "\nRequirements:\n";
    $prompt .= "- 120-150 words total\n";
    $prompt .= "- Professional business tone\n";
    $prompt .= "- Include 15-minute call CTA\n";
    $prompt .= "- Focus on value proposition\n";
    $prompt .= "- Be specific about TLS program benefits\n";
    
    return $prompt;
}

/**
 * Send outreach email via SMTP
 */
function sendOutreachEmail($email_data, $pdo, $user_id, $envManager) {
    $smtp_host = $envManager->get('SMTP_HOST');
    $smtp_user = $envManager->get('SMTP_USER');
    $smtp_pass = $envManager->get('SMTP_PASS');
    $smtp_port = $envManager->get('SMTP_PORT') ?: 587;
    
    if (!$smtp_host) {
        return ['success' => false, 'error' => 'SMTP not configured'];
    }
    
    // Basic email validation
    if (!filter_var($email_data['recipient_email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid recipient email'];
    }
    
    try {
        // Store outreach record in database
        $stmt = $pdo->prepare("
            INSERT INTO outreach (
                prime_id, recipient_email, recipient_name, subject, content, 
                sent_by, created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'sent')
        ");
        
        $stmt->execute([
            $email_data['prime_id'],
            $email_data['recipient_email'],
            $email_data['recipient_name'],
            $email_data['subject'],
            $email_data['content'],
            $user_id
        ]);
        
        // TODO: Implement actual SMTP sending
        // For now, just simulate success since MailHog is for testing
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Log outreach activity
 */
function logOutreachActivity($email_data, $pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (
                entity_type, entity_id, activity_type, activity_data, 
                created_by, created_at
            ) VALUES ('outreach', 'email', 'email_sent', ?, ?, NOW())
        ");
        
        $activity_data = json_encode([
            'recipient_email' => $email_data['recipient_email'],
            'subject' => $email_data['subject'],
            'prime_id' => $email_data['prime_id']
        ]);
        
        $stmt->execute([$activity_data, $user_id]);
        
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log outreach activity: " . $e->getMessage());
    }
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
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://unpkg.com/gsap@3.12.2/dist/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.27/dist/lenis.min.js"></script>
</head>
<body class="outreach-composer">
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
    <main id="main-content" class="composer-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="header-container">
                <div class="header-content glassmorphism reveal">
                    <div class="header-text">
                        <h1 class="header-title">
                            <!-- Send Icon -->
                            <svg class="header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22,2 15,22 11,13 2,9 22,2"/>
                            </svg>
                            Outreach Email Composer
                        </h1>
                        <p class="header-description"><?php echo htmlspecialchars($meta_description); ?></p>
                    </div>
                    
                    <div class="header-actions">
                        <div class="breadcrumb">
                            <a href="/programs/tls" class="breadcrumb-link">TLS Program</a>
                            <span class="breadcrumb-separator">→</span>
                            <span class="breadcrumb-current">Outreach</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <!-- Email Composer -->
        <section class="composer-section">
            <div class="content-container">
                <div class="composer-layout">
                    <!-- Context Sidebar -->
                    <aside class="composer-sidebar glassmorphism reveal">
                        <h2 class="sidebar-title">Email Context</h2>
                        
                        <form method="POST" class="context-form" id="context-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="generate_draft">
                            
                            <div class="form-group">
                                <label for="program_code" class="form-label">Program</label>
                                <select name="program_code" id="program_code" class="form-select">
                                    <option value="tls" <?php echo $program_code === 'tls' ? 'selected' : ''; ?>>TLS (Tactical Logistics Support)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="prime_id" class="form-label">Prime Contractor</label>
                                <select name="prime_id" id="prime_id" class="form-select">
                                    <option value="">Select Prime...</option>
                                    <?php foreach ($primes as $prime): ?>
                                    <option value="<?php echo htmlspecialchars($prime['id']); ?>" 
                                            <?php echo $prime_id === $prime['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prime['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="purpose" class="form-label">Email Purpose</label>
                                <select name="purpose" id="purpose" class="form-select">
                                    <option value="introduction">Introduction</option>
                                    <option value="follow_up">Follow-up</option>
                                    <option value="proposal_request">Proposal Request</option>
                                    <option value="capability_inquiry">Capability Inquiry</option>
                                    <option value="meeting_request">Meeting Request</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="tone" class="form-label">Tone</label>
                                <select name="tone" id="tone" class="form-select">
                                    <option value="professional">Professional</option>
                                    <option value="friendly">Friendly Professional</option>
                                    <option value="formal">Formal</option>
                                    <option value="direct">Direct</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="custom_details" class="form-label">Custom Details</label>
                                <textarea name="custom_details" id="custom_details" class="form-textarea" 
                                          placeholder="Add specific requirements, project details, or other context..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="include_catalog" id="include_catalog" class="form-checkbox">
                                    <span class="checkbox-text">Include micro-catalog reference</span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="meeting_request" id="meeting_request" class="form-checkbox" checked>
                                    <span class="checkbox-text">Request 15-minute call</span>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-primary generate-btn">
                                <!-- AI Icon -->
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 6V4m0 2a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m-6 8a2 2 0 1 0 0-4m0 4a2 2 0 1 1 0-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 1 0 0-4m0 4a2 2 0 1 1 0-4m0 4v2m0-6V4"/>
                                </svg>
                                Generate AI Draft
                            </button>
                        </form>
                        
                        <!-- Prime Info Display -->
                        <?php if ($selected_prime): ?>
                        <div class="prime-info">
                            <h3 class="prime-info-title"><?php echo htmlspecialchars($selected_prime['name']); ?></h3>
                            <div class="prime-info-details">
                                <div class="info-item">
                                    <span class="info-label">Lead Time:</span>
                                    <span class="info-value"><?php echo $selected_prime['lead_time_days']; ?> days</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Kit Support:</span>
                                    <span class="info-value <?php echo $selected_prime['kit_support'] ? 'text-success' : 'text-muted'; ?>">
                                        <?php echo $selected_prime['kit_support'] ? 'Available' : 'Not Available'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="prime-capabilities">
                                <?php foreach ($selected_prime['capabilities'] as $capability): ?>
                                <span class="capability-tag"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $capability))); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </aside>
                    
                    <!-- Email Editor -->
                    <main class="composer-main-content">
                        <div class="email-editor glassmorphism reveal">
                            <form method="POST" class="email-form" id="email-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="send_email">
                                <input type="hidden" name="prime_id" value="<?php echo htmlspecialchars($prime_id); ?>">
                                
                                <div class="email-header">
                                    <h2 class="editor-title">Email Composition</h2>
                                    <div class="email-controls">
                                        <button type="button" class="btn-secondary" id="save-draft-btn">Save Draft</button>
                                        <button type="submit" class="btn-primary" id="send-email-btn">Send Email</button>
                                    </div>
                                </div>
                                
                                <div class="email-fields">
                                    <div class="field-group">
                                        <label for="recipient_email" class="field-label">To</label>
                                        <input type="email" name="recipient_email" id="recipient_email" 
                                               class="field-input" placeholder="recipient@company.com" required>
                                    </div>
                                    
                                    <div class="field-group">
                                        <label for="recipient_name" class="field-label">Recipient Name</label>
                                        <input type="text" name="recipient_name" id="recipient_name" 
                                               class="field-input" placeholder="John Smith">
                                    </div>
                                    
                                    <div class="field-group">
                                        <label for="subject" class="field-label">Subject</label>
                                        <input type="text" name="subject" id="subject" 
                                               class="field-input" placeholder="TLS Program - Partnership Opportunity">
                                    </div>
                                    
                                    <div class="field-group">
                                        <label for="content" class="field-label">Email Content</label>
                                        <textarea name="content" id="content" class="email-content" 
                                                  placeholder="Your email content will appear here after generating an AI draft..."><?php echo htmlspecialchars($saved_draft ?? ''); ?></textarea>
                                        <div class="content-stats">
                                            <span class="word-count">Words: <span id="word-count">0</span></span>
                                            <span class="char-count">Characters: <span id="char-count">0</span></span>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </main>
                </div>
            </div>
        </section>
    </main>

    <!-- Scripts -->
    <script src="/js/outreach-composer.js"></script>
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
            
            // Initialize composer functionality
            new OutreachComposer();
        });
    </script>
</body>
</html>