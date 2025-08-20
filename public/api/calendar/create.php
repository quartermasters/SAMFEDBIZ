<?php
###############################################################################
# Calendar Creation API Endpoint
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

$title = $input['title'] ?? '';
$description = $input['notes'] ?? '';
$attendees = $input['attendees'] ?? [];
$duration = $input['duration'] ?? 30; // minutes
$context = $input['context'] ?? [];

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit();
}

// Mock calendar event creation (in production, this would integrate with Google Calendar API)
$eventId = 'evt_' . uniqid();
$startTime = new DateTime('+1 day');
$startTime->setTime(14, 0); // Default to 2 PM tomorrow

$endTime = clone $startTime;
$endTime->add(new DateInterval('PT' . $duration . 'M'));

$response = [
    'success' => true,
    'event' => [
        'id' => $eventId,
        'title' => $title,
        'description' => $description,
        'start_time' => $startTime->format('c'),
        'end_time' => $endTime->format('c'),
        'attendees' => $attendees,
        'calendar_url' => "https://calendar.google.com/calendar/event?action=TEMPLATE&text=" . urlencode($title),
        'context' => $context
    ],
    'message' => 'Calendar event template created. Click the calendar URL to add to your Google Calendar.'
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>