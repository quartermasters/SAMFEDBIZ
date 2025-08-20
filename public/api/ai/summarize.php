<?php
###############################################################################
# AI Summarization API Endpoint
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

$content = $input['content'] ?? '';
$type = $input['type'] ?? 'general'; // general, solicitation, document, news
$context = $input['context'] ?? [];
$length = $input['length'] ?? 'medium'; // short, medium, long

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Content is required']);
    exit();
}

// Mock AI summarization (in production, this would call OpenAI/Gemini)
function generateSummary($content, $type, $length) {
    $contentLength = strlen($content);
    
    switch ($type) {
        case 'solicitation':
            return [
                'executive_summary' => 'This solicitation seeks qualified contractors for federal services with specific technical requirements and delivery timelines.',
                'key_requirements' => [
                    'Technical specifications compliance',
                    'Security clearance requirements',
                    'Past performance demonstration',
                    'Cost-effective solution delivery'
                ],
                'opportunities' => [
                    'High-value contract potential',
                    'Long-term relationship building',
                    'Expansion into new market segments'
                ],
                'risks' => [
                    'Competitive bidding environment',
                    'Stringent compliance requirements',
                    'Tight delivery schedules'
                ],
                'next_actions' => [
                    'Review technical specifications in detail',
                    'Assess team capabilities and capacity',
                    'Prepare capability statement',
                    'Identify potential teaming partners'
                ]
            ];
            
        case 'document':
            return [
                'executive_summary' => 'This document provides comprehensive guidance on federal contracting processes and requirements for successful bid submissions.',
                'main_points' => [
                    'Regulatory compliance framework',
                    'Proposal preparation guidelines',
                    'Evaluation criteria breakdown',
                    'Post-award management requirements'
                ],
                'actionable_insights' => [
                    'Focus on past performance narratives',
                    'Emphasize technical differentiators',
                    'Ensure full regulatory compliance',
                    'Develop strong cost justification'
                ]
            ];
            
        case 'news':
            return [
                'executive_summary' => 'Recent federal contracting developments show increased emphasis on innovation and cybersecurity capabilities.',
                'key_developments' => [
                    'New procurement vehicles announced',
                    'Updated security requirements',
                    'Increased small business set-asides',
                    'Technology modernization initiatives'
                ],
                'market_implications' => [
                    'Growing demand for cyber services',
                    'Shift toward agile delivery methods',
                    'Emphasis on commercial solutions',
                    'Increased competition in traditional sectors'
                ]
            ];
            
        default:
            return [
                'executive_summary' => 'Content analysis reveals key themes around federal business development opportunities and strategic considerations.',
                'main_themes' => [
                    'Strategic positioning',
                    'Capability development',
                    'Market analysis',
                    'Competitive landscape'
                ],
                'recommendations' => [
                    'Develop targeted capability statements',
                    'Build strategic partnerships',
                    'Monitor market trends',
                    'Invest in key differentiators'
                ]
            ];
    }
}

$summary = generateSummary($content, $type, $length);

$response = [
    'success' => true,
    'summary' => $summary,
    'metadata' => [
        'original_length' => strlen($content),
        'original_words' => str_word_count($content),
        'summary_type' => $type,
        'summary_length' => $length,
        'generated_at' => date('c'),
        'confidence_score' => 0.85 // Mock confidence score
    ],
    'tags' => [
        'federal_contracting',
        'business_development',
        strtolower($type),
        'ai_generated'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>