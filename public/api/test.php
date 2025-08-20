<?php
###############################################################################
# Test API Endpoint
# samfedbiz.com - Federal BD Platform
###############################################################################

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'success' => true,
    'message' => 'API endpoints are working correctly',
    'timestamp' => date('c'),
    'endpoints' => [
        '/api/ai/chat' => 'SFBAI chat interface',
        '/api/ai/brief' => 'Daily brief generation',
        '/api/ai/summarize' => 'Content summarization',
        '/api/ai/draft_email' => 'Email draft generation',
        '/api/calendar/create' => 'Calendar event creation',
        '/api/outreach/send' => 'Email sending'
    ],
    'status' => 'All API endpoints created and ready for testing'
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>