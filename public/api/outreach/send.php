<?php
###############################################################################
# Outreach Email Send API Endpoint
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

$to = $input['recipient_email'] ?? '';
$toName = $input['recipient_name'] ?? '';
$subject = $input['subject'] ?? '';
$content = $input['content'] ?? '';
$context = $input['context'] ?? [];

// Validate required fields
if (empty($to) || empty($subject) || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Recipient email, subject, and content are required']);
    exit();
}

// Validate email format
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit();
}

// Mock email sending (in production, this would use SMTP)
// For now, simulate sending by logging to a file
$emailLog = [
    'timestamp' => date('c'),
    'to' => $to,
    'to_name' => $toName,
    'subject' => $subject,
    'content' => $content,
    'context' => $context,
    'status' => 'sent'
];

// Create logs directory if it doesn't exist
if (!is_dir('../../storage/logs')) {
    mkdir('../../storage/logs', 0755, true);
}

// Log the email (in production, this would be sent via SMTP)
file_put_contents('../../storage/logs/outreach_emails.log', 
    json_encode($emailLog) . "\n", FILE_APPEND | LOCK_EX);

$response = [
    'success' => true,
    'message' => 'Email sent successfully',
    'email_id' => 'email_' . uniqid(),
    'recipient' => [
        'email' => $to,
        'name' => $toName
    ],
    'timestamp' => date('c'),
    'note' => 'This is a simulated send for testing. In production, emails would be sent via SMTP.'
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>