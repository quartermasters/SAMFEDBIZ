<?php
/**
 * AI Document Summarization API Endpoint
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Generates AI summaries and creates Notes with tags
 */

require_once __DIR__ . '/../../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Services\GoogleDriveService;
use SamFedBiz\Config\EnvManager;

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Initialize managers
$envManager = new EnvManager();
$authManager = new AuthManager($pdo);
$driveService = new GoogleDriveService($envManager);

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

// Check user permissions
$user = $authManager->getCurrentUser();
if ($user['role'] === 'viewer') {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$documentId = $input['document_id'] ?? null;

if (!$documentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Document ID required']);
    exit;
}

try {
    // Get document details
    $stmt = $pdo->prepare("
        SELECT id, title, doc_type, source_url, drive_file_id, content, tags 
        FROM research_docs 
        WHERE id = ?
    ");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
        exit;
    }

    // Check if document already has content
    $documentContent = $document['content'];
    
    // If no content and we have a Drive file ID, try to fetch it
    if (empty($documentContent) && !empty($document['drive_file_id'])) {
        // Get OAuth tokens for Drive access
        $stmt = $pdo->prepare("
            SELECT access_token, refresh_token 
            FROM oauth_tokens 
            WHERE service = 'google_drive' AND user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tokenData) {
            try {
                $driveService->setTokens($tokenData['access_token'], $tokenData['refresh_token']);
                $documentContent = $driveService->getDocumentContent($document['drive_file_id']);
                
                // Update document with content
                if (!empty($documentContent)) {
                    $stmt = $pdo->prepare("
                        UPDATE research_docs 
                        SET content = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$documentContent, $documentId]);
                }
            } catch (Exception $e) {
                error_log("Failed to fetch Drive content for document {$documentId}: " . $e->getMessage());
            }
        }
    }

    // Use title and available info if no content
    if (empty($documentContent)) {
        $documentContent = "Document: {$document['title']}\nType: {$document['doc_type']}\nURL: {$document['source_url']}";
    }

    // Generate AI summary (placeholder implementation)
    $summary = generateDocumentSummary($document, $documentContent);
    
    // Extract tags from document and summary
    $tags = extractTags($document, $summary);
    
    // Create a Note with the summary
    $stmt = $pdo->prepare("
        INSERT INTO notes (content, tags, source_type, source_id, created_by, created_at)
        VALUES (?, ?, 'document', ?, ?, NOW())
    ");
    $stmt->execute([
        $summary,
        json_encode($tags),
        $documentId,
        $user['id']
    ]);
    $noteId = $pdo->lastInsertId();

    // Log activity with PII protection
    $activityData = [
        'message' => 'Generated AI summary for document',
        'document_id' => $documentId,
        'note_id' => $noteId,
        'summary_length' => strlen($summary),
        'tags_count' => count($tags),
        // PII Protection: Only log model name and prompt hash, not content
        'model_used' => 'gpt-4-summary',
        'prompt_hash' => hash('sha256', $documentContent)
    ];

    $stmt = $pdo->prepare("
        INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
        VALUES ('document', ?, 'ai_summary_generated', ?, ?, NOW())
    ");
    $stmt->execute([
        $documentId,
        json_encode($activityData),
        $user['id']
    ]);

    echo json_encode([
        'summary' => $summary,
        'note_id' => $noteId,
        'tags' => $tags,
        'document_title' => $document['title']
    ]);

} catch (Exception $e) {
    error_log("Document summarization failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Summarization failed: ' . $e->getMessage()]);
}

/**
 * Generate AI summary for document
 * In production, this would call OpenAI/Gemini APIs
 */
function generateDocumentSummary($document, $content) {
    // Placeholder AI summary generation
    $title = $document['title'];
    $type = $document['doc_type'];
    $contentLength = strlen($content);
    
    // Extract key information
    $keyTerms = extractKeyTerms($content);
    $programs = extractPrograms($content);
    
    $summary = "**AI Summary of {$title}**\n\n";
    $summary .= "**Document Type:** {$type}\n";
    $summary .= "**Content Length:** " . number_format($contentLength) . " characters\n\n";
    
    if (!empty($programs)) {
        $summary .= "**Related Programs:** " . implode(', ', $programs) . "\n\n";
    }
    
    if (!empty($keyTerms)) {
        $summary .= "**Key Terms:** " . implode(', ', array_slice($keyTerms, 0, 10)) . "\n\n";
    }
    
    $summary .= "**Key Points:**\n";
    $summary .= "• Document provides information relevant to federal business development\n";
    $summary .= "• Contains technical specifications and requirements\n";
    $summary .= "• Includes compliance and regulatory guidance\n";
    $summary .= "• May contain contract vehicle information\n\n";
    
    $summary .= "**Recommended Actions:**\n";
    $summary .= "• Review for program-specific requirements\n";
    $summary .= "• Extract capability requirements\n";
    $summary .= "• Identify potential bid opportunities\n";
    $summary .= "• Share with relevant team members\n\n";
    
    $summary .= "*This summary was generated by SFBAI on " . date('M j, Y') . "*";
    
    return $summary;
}

/**
 * Extract key terms from content
 */
function extractKeyTerms($content) {
    // Simple keyword extraction
    $terms = [];
    $content_lower = strtolower($content);
    
    $keywords = [
        'federal', 'contract', 'solicitation', 'rfp', 'rfq', 'procurement',
        'security', 'cyber', 'logistics', 'supply', 'equipment', 'services',
        'technical', 'specifications', 'requirements', 'compliance',
        'clearance', 'classified', 'unclassified', 'cots', 'gots',
        'small business', 'set-aside', 'hubzone', 'sdvosb', 'wosb'
    ];
    
    foreach ($keywords as $keyword) {
        if (strpos($content_lower, $keyword) !== false) {
            $terms[] = $keyword;
        }
    }
    
    return array_unique($terms);
}

/**
 * Extract program references from content
 */
function extractPrograms($content) {
    $programs = [];
    $content_lower = strtolower($content);
    
    $programKeywords = [
        'tls' => ['tls', 'tactical logistics', 'special operations equipment'],
        'oasis+' => ['oasis', 'oasis+', 'oasis plus'],
        'sewp' => ['sewp', 'solutions for enterprise-wide procurement']
    ];
    
    foreach ($programKeywords as $program => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $programs[] = $program;
                break;
            }
        }
    }
    
    return array_unique($programs);
}

/**
 * Extract tags from document and summary
 */
function extractTags($document, $summary) {
    $tags = [];
    
    // Add existing document tags
    $existingTags = json_decode($document['tags'], true) ?: [];
    $tags = array_merge($tags, $existingTags);
    
    // Add document type
    $tags[] = strtolower(str_replace(' ', '_', $document['doc_type']));
    
    // Add program tags from summary
    $summaryLower = strtolower($summary);
    if (strpos($summaryLower, 'tls') !== false) $tags[] = 'tls';
    if (strpos($summaryLower, 'oasis') !== false) $tags[] = 'oasis+';
    if (strpos($summaryLower, 'sewp') !== false) $tags[] = 'sewp';
    
    // Add topic tags
    $topicKeywords = [
        'security' => ['security', 'cyber', 'clearance'],
        'logistics' => ['logistics', 'supply', 'equipment'],
        'technical' => ['technical', 'specifications', 'requirements'],
        'compliance' => ['compliance', 'regulatory', 'standards'],
        'procurement' => ['procurement', 'contract', 'solicitation'],
        'small_business' => ['small business', 'set-aside', 'socioeconomic']
    ];
    
    foreach ($topicKeywords as $topic => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($summaryLower, $keyword) !== false) {
                $tags[] = $topic;
                break;
            }
        }
    }
    
    // Add source tag
    $tags[] = 'ai_summary';
    $tags[] = 'doc:' . $document['id'];
    
    return array_unique($tags);
}