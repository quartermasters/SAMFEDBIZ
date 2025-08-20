<?php
/**
 * SFBAI Chat API Endpoint
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles chat messages and slash commands
 */

require_once __DIR__ . '/../../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Core\ProgramRegistry;
use SamFedBiz\Config\EnvManager;

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Initialize managers
$envManager = new EnvManager();
$authManager = new AuthManager($pdo);
$programRegistry = new ProgramRegistry($pdo);

// Check authentication
if (!$authManager->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Check CSRF token
if (!$authManager->validateCSRFToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$message = trim($input['message'] ?? '');
$context = $input['context'] ?? [];
$isSlashCommand = $input['is_slash_command'] ?? false;
$history = $input['history'] ?? [];

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

$user = $authManager->getCurrentUser();

// Handle slash commands
if ($isSlashCommand || str_starts_with($message, '/')) {
    $result = handleSlashCommand($message, $context, $programRegistry, $pdo);
    echo json_encode($result);
    exit;
}

// Handle regular chat
try {
    // Build context for AI
    $aiContext = [
        'user_role' => $user['role'],
        'page_context' => $context,
        'platform' => 'samfedbiz.com',
        'domain' => 'federal business development',
        'capabilities' => ['research', 'analysis', 'summarization', 'email_drafting']
    ];

    // Simulate AI response (in production, this would call OpenAI/Gemini)
    $response = generateAIResponse($message, $aiContext, $history);
    
    // Log the interaction
    logChatInteraction($pdo, $user['id'], $message, $response, $context);
    
    echo json_encode([
        'response' => $response,
        'actions' => determineActions($message, $response),
        'context' => $context
    ]);

} catch (Exception $e) {
    error_log("SFBAI Chat Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle slash commands
 */
function handleSlashCommand($message, $context, $programRegistry, $pdo) {
    $parts = explode(' ', trim($message));
    $command = strtolower($parts[0]);
    $args = array_slice($parts, 1);
    
    switch ($command) {
        case '/opps':
            return handleOppsCommand($args, $context, $programRegistry);
            
        case '/brief':
            return handleBriefCommand($args, $context, $pdo);
            
        case '/summarize':
            return handleSummarizeCommand($args, $context);
            
        case '/draft':
            return handleDraftCommand($args, $context);
            
        case '/catalog':
            return handleCatalogCommand($args, $context, $programRegistry);
            
        case '/schedule':
            return handleScheduleCommand($args, $context);
            
        case '/?':
        case '/help':
            return handleHelpCommand();
            
        default:
            return [
                'response' => "Unknown command: {$command}. Type /? for help.",
                'actions' => []
            ];
    }
}

/**
 * Handle /opps command - return closing-soon opportunities
 */
function handleOppsCommand($args, $context, $programRegistry) {
    $filter = implode(' ', $args);
    $program = $context['program'] ?? null;
    
    // Get all opportunities
    $opportunities = [];
    $valid_programs = ['tls', 'oasis+', 'sewp'];
    
    foreach ($valid_programs as $prog) {
        if ($program && $program !== $prog) continue;
        
        $adapter = $programRegistry->getAdapter($prog);
        if (!$adapter) continue;
        
        try {
            $rawOpps = $adapter->fetchSolicitations();
            foreach ($rawOpps as $rawOpp) {
                $normalized = $adapter->normalize($rawOpp);
                $normalized['program'] = $prog;
                $normalized['program_name'] = $adapter->name();
                $opportunities[] = $normalized;
            }
        } catch (Exception $e) {
            error_log("Error fetching opportunities for {$prog}: " . $e->getMessage());
        }
    }
    
    // Filter and sort by close date
    $filteredOpps = array_filter($opportunities, function($opp) use ($filter) {
        if (empty($filter)) return true;
        
        $searchText = strtolower($opp['title'] . ' ' . $opp['agency'] . ' ' . $opp['program_name']);
        return strpos($searchText, strtolower($filter)) !== false;
    });
    
    // Sort by close date (ascending - soonest first)
    usort($filteredOpps, function($a, $b) {
        return strtotime($a['close_date']) - strtotime($b['close_date']);
    });
    
    // Take top 10
    $topOpps = array_slice($filteredOpps, 0, 10);
    
    if (empty($topOpps)) {
        return [
            'response' => $filter ? 
                "No opportunities found matching '{$filter}'." : 
                "No opportunities found.",
            'actions' => []
        ];
    }
    
    // Format response
    $response = "**Closing Soon Opportunities:**\n\n";
    
    foreach ($topOpps as $opp) {
        $closeDate = date('M j, Y', strtotime($opp['close_date']));
        $daysUntilClose = ceil((strtotime($opp['close_date']) - time()) / (60 * 60 * 24));
        
        $response .= "**{$opp['title']}**\n";
        $response .= "• Program: {$opp['program_name']}\n";
        $response .= "• Agency: {$opp['agency']}\n";
        $response .= "• Closes: {$closeDate}";
        
        if ($daysUntilClose >= 0) {
            $response .= " ({$daysUntilClose} days)\n";
        } else {
            $response .= " (CLOSED)\n";
        }
        
        $response .= "• Number: `{$opp['opp_no']}`\n";
        $response .= "• [View Details](/solicitations/" . urlencode($opp['opp_no']) . ")\n\n";
    }
    
    $actions = [
        [
            'label' => 'Export List',
            'action' => 'export_opportunities',
            'data' => ['opportunities' => $topOpps]
        ],
        [
            'label' => 'Set Reminders',
            'action' => 'set_reminders',
            'data' => ['opportunities' => $topOpps]
        ]
    ];
    
    return [
        'response' => $response,
        'actions' => $actions
    ];
}

/**
 * Handle /brief command
 */
function handleBriefCommand($args, $context, $pdo) {
    $program = $args[0] ?? $context['program'] ?? null;
    
    if (!$program) {
        return [
            'response' => 'Please specify a program: /brief tls, /brief oasis+, or /brief sewp',
            'actions' => []
        ];
    }
    
    // Get latest brief for program
    $stmt = $pdo->prepare("
        SELECT content, created_at 
        FROM daily_briefs 
        WHERE JSON_CONTAINS(tags, JSON_QUOTE(?))
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$program]);
    $brief = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brief) {
        return [
            'response' => "No recent brief found for {$program}. Briefs are generated daily at 06:00 Dubai time.",
            'actions' => []
        ];
    }
    
    $briefDate = date('M j, Y', strtotime($brief['created_at']));
    $response = "**Latest {$program} Brief ({$briefDate}):**\n\n";
    $response .= $brief['content'];
    
    return [
        'response' => $response,
        'actions' => [
            [
                'label' => 'View Full Archive',
                'action' => 'view_briefs_archive',
                'data' => ['program' => $program]
            ]
        ]
    ];
}

/**
 * Handle other commands (placeholder implementations)
 */
function handleSummarizeCommand($args, $context) {
    return [
        'response' => 'Summarization feature coming soon. Please use the "Generate AI Summary" button on solicitation detail pages.',
        'actions' => []
    ];
}

function handleDraftCommand($args, $context) {
    return [
        'response' => 'Email drafting feature coming soon. Please use the "Draft Email" button on holder profile pages.',
        'actions' => []
    ];
}

function handleCatalogCommand($args, $context, $programRegistry) {
    $program = $context['program'] ?? null;
    
    if (!$program) {
        return [
            'response' => 'Please specify a program context or visit a program page to view catalogs.',
            'actions' => []
        ];
    }
    
    $adapter = $programRegistry->getAdapter($program);
    if (!$adapter) {
        return [
            'response' => "Invalid program: {$program}",
            'actions' => []
        ];
    }
    
    $catalogType = $program === 'tls' ? 'micro-catalog' : 'capability sheet';
    
    return [
        'response' => "**{$adapter->name()} {$catalogType}:**\n\nTo view the full {$catalogType}, visit a specific holder profile page where you can print or export the catalog.",
        'actions' => [
            [
                'label' => 'View Holders',
                'action' => 'view_holders',
                'data' => ['program' => $program]
            ]
        ]
    ];
}

function handleScheduleCommand($args, $context) {
    return [
        'response' => 'Meeting scheduling feature coming soon. Please use the "Schedule Meeting" button on holder profile pages.',
        'actions' => []
    ];
}

function handleHelpCommand() {
    return [
        'response' => "**Available Commands:**\n\n" .
                     "`/opps [filter]` - Show closing-soon opportunities\n" .
                     "`/brief [program]` - Show latest daily brief\n" .
                     "`/summarize` - Summarize current content\n" .
                     "`/draft` - Draft email\n" .
                     "`/catalog` - View program catalog\n" .
                     "`/schedule` - Schedule meeting\n" .
                     "`/?` - Show this help",
        'actions' => []
    ];
}

/**
 * Generate AI response (placeholder)
 */
function generateAIResponse($message, $context, $history) {
    // In production, this would call OpenAI or Gemini APIs
    // For now, return a contextual response
    
    $responses = [
        "I understand you're asking about '{$message}'. Based on the current context, I can help you with federal BD intelligence and program analysis.",
        "Let me help you with that. As your SFBAI assistant, I can provide insights about federal contracting opportunities and program details.",
        "I'm here to assist with your federal business development needs. Feel free to ask about programs, opportunities, or use slash commands for quick actions."
    ];
    
    return $responses[array_rand($responses)];
}

/**
 * Determine available actions based on message and response
 */
function determineActions($message, $response) {
    $actions = [];
    
    if (stripos($message, 'email') !== false || stripos($message, 'draft') !== false) {
        $actions[] = [
            'label' => 'Copy to Outreach',
            'action' => 'copy_to_outreach',
            'data' => ['content' => $response]
        ];
    }
    
    if (stripos($message, 'meeting') !== false || stripos($message, 'schedule') !== false) {
        $actions[] = [
            'label' => 'Schedule Meeting',
            'action' => 'schedule_meeting',
            'data' => []
        ];
    }
    
    if (stripos($message, 'note') !== false || stripos($message, 'save') !== false) {
        $actions[] = [
            'label' => 'Save Note',
            'action' => 'save_note',
            'data' => ['content' => $response]
        ];
    }
    
    return $actions;
}

/**
 * Log chat interaction
 */
function logChatInteraction($pdo, $userId, $message, $response, $context) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (user_id, message, response, context, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $message,
            $response,
            json_encode($context)
        ]);
    } catch (Exception $e) {
        error_log("Failed to log chat interaction: " . $e->getMessage());
    }
}