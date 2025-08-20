<?php
###############################################################################
# AI Email Draft API Endpoint
# samfedbiz.com - Federal BD Platform
# Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM
###############################################################################

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Basic CSRF protection
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? '';
if (empty($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token required']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit();
}

$purpose = $input['purpose'] ?? 'introduction';
$program = $input['program'] ?? '';
$holder = $input['holder'] ?? '';
$context = $input['context'] ?? [];
$tone = $input['tone'] ?? 'professional';

// Generate AI-powered email draft (mock implementation)
$drafts = [
    'introduction' => [
        'subject' => 'Partnership Opportunity - Federal BD Collaboration',
        'content' => "Dear [Recipient],\n\nI hope this message finds you well. I'm reaching out to explore potential partnership opportunities in the federal business development space.\n\nGiven [Company]'s expertise in [specific area], I believe there may be synergies with our current federal contracting initiatives, particularly in the {$program} program space.\n\nWould you be available for a brief 15-minute call next week to discuss how we might collaborate on upcoming opportunities?\n\nI look forward to your response.\n\nBest regards,\n[Your Name]"
    ],
    'follow_up' => [
        'subject' => 'Following Up - Federal Partnership Discussion',
        'content' => "Dear [Recipient],\n\nI wanted to follow up on our previous conversation regarding potential collaboration in the federal space.\n\nAs we discussed, there are several upcoming opportunities in the {$program} program that could benefit from our combined capabilities.\n\nI've attached our capability statement for your review. Would you be available for a follow-up call this week to discuss next steps?\n\nThank you for your time and consideration.\n\nBest regards,\n[Your Name]"
    ],
    'proposal_request' => [
        'subject' => 'RFP Collaboration Opportunity - [Opportunity Number]',
        'content' => "Dear [Recipient],\n\nI hope you're doing well. We've identified an upcoming RFP in the {$program} program that aligns perfectly with both of our capabilities.\n\nGiven [Company]'s strengths in [specific area] and our experience with [relevant experience], I believe a joint response would be highly competitive.\n\nThe proposal deadline is [date], so time is of the essence. Could we schedule a call this week to discuss teaming arrangements and proposal strategy?\n\nI'm happy to share the RFP details and our initial analysis.\n\nLooking forward to hearing from you.\n\nBest regards,\n[Your Name]"
    ]
];

$selectedDraft = $drafts[$purpose] ?? $drafts['introduction'];

// Customize based on context
if (!empty($program)) {
    $selectedDraft['content'] = str_replace('{$program}', strtoupper($program), $selectedDraft['content']);
    $selectedDraft['subject'] = str_replace('[Program]', strtoupper($program), $selectedDraft['subject']);
}

if (!empty($holder)) {
    $selectedDraft['content'] = str_replace('[Company]', $holder, $selectedDraft['content']);
}

// Word count (target 120-150 words)
$wordCount = str_word_count(strip_tags($selectedDraft['content']));

$response = [
    'success' => true,
    'draft' => [
        'subject' => $selectedDraft['subject'],
        'content' => $selectedDraft['content'],
        'word_count' => $wordCount,
        'purpose' => $purpose,
        'tone' => $tone
    ],
    'suggestions' => [
        'Replace [Recipient] with the actual contact name',
        'Update [Company] with the specific company name',
        'Add specific capability details where indicated',
        'Include relevant past performance examples'
    ],
    'metadata' => [
        'generated_at' => date('c'),
        'target_range' => '120-150 words',
        'current_count' => $wordCount,
        'within_range' => ($wordCount >= 120 && $wordCount <= 150)
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>