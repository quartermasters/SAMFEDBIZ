<?php
###############################################################################
# AI Brief Generation API Endpoint
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

$program = $input['program'] ?? 'all';
$date = $input['date'] ?? date('Y-m-d');
$context = $input['context'] ?? [];

// Mock brief generation (in production, this would aggregate real data)
function generateBrief($program, $date) {
    $briefData = [
        'tls' => [
            'program_name' => 'Tactical Law Enforcement Support',
            'opportunities' => [
                ['title' => 'SOE Kit Procurement', 'value' => '$1.2M', 'close_date' => '2025-08-25'],
                ['title' => 'Body Armor Systems', 'value' => '$890K', 'close_date' => '2025-08-30']
            ],
            'market_intel' => [
                'New RFP postings increased 15% this week',
                'Strong demand for integrated SOE solutions',
                'Law enforcement budgets showing stability'
            ],
            'what_it_means' => 'Market conditions favor tactical equipment providers with proven SOE capabilities. Focus on integrated solutions rather than individual components.',
            'next_actions' => [
                'Review SOE-2025-001 requirements by COB today',
                'Update capability statements for Q4 submissions',
                'Schedule meetings with top 3 law enforcement contacts'
            ]
        ],
        'oasis+' => [
            'program_name' => 'OASIS+',
            'opportunities' => [
                ['title' => 'Cybersecurity Consulting', 'value' => '$2.1M', 'close_date' => '2025-09-02'],
                ['title' => 'Data Analytics Services', 'value' => '$1.5M', 'close_date' => '2025-09-05']
            ],
            'market_intel' => [
                'Professional services demand up 22%',
                'Cybersecurity pool showing highest activity',
                'Small business set-asides increasing'
            ],
            'what_it_means' => 'OASIS+ continues to be the preferred vehicle for professional services. Cybersecurity and data analytics are key growth areas.',
            'next_actions' => [
                'Submit capability updates for SB pool',
                'Prepare white papers on zero-trust architecture',
                'Connect with prime contractors for teaming'
            ]
        ],
        'sewp' => [
            'program_name' => 'SEWP VI',
            'opportunities' => [
                ['title' => 'Cloud Migration Services', 'value' => '$3.2M', 'close_date' => '2025-08-28'],
                ['title' => 'AI/ML Platform Deployment', 'value' => '$1.8M', 'close_date' => '2025-09-01']
            ],
            'market_intel' => [
                'Cloud services dominating Group C activity',
                'AI/ML solutions seeing 40% growth',
                'Hardware procurement stabilizing'
            ],
            'what_it_means' => 'SEWP buyers are prioritizing cloud and AI solutions. Traditional hardware sales declining in favor of services.',
            'next_actions' => [
                'Update OEM authorizations for cloud services',
                'Develop AI/ML capability demonstrations',
                'Review pricing models for competitive positioning'
            ]
        ]
    ];
    
    if ($program === 'all') {
        $combined = [
            'program_name' => 'All Programs Summary',
            'opportunities' => [],
            'market_intel' => [
                'Federal IT spending up 8% year-over-year',
                'Cybersecurity investments accelerating',
                'Professional services showing strongest growth'
            ],
            'what_it_means' => 'Overall federal market remains robust with clear trends toward technology modernization and security enhancement.',
            'next_actions' => [
                'Prioritize cybersecurity and cloud capabilities',
                'Strengthen small business partnerships',
                'Develop integrated solution offerings'
            ]
        ];
        
        foreach ($briefData as $prog => $data) {
            $combined['opportunities'] = array_merge($combined['opportunities'], $data['opportunities']);
        }
        
        return $combined;
    }
    
    return $briefData[$program] ?? $briefData['tls'];
}

$brief = generateBrief($program, $date);

$response = [
    'success' => true,
    'brief' => [
        'date' => $date,
        'program' => $program,
        'program_name' => $brief['program_name'],
        'executive_summary' => "Daily intelligence brief for {$brief['program_name']} as of " . date('F j, Y', strtotime($date)),
        'opportunities' => $brief['opportunities'],
        'market_intelligence' => $brief['market_intel'],
        'analysis' => [
            'what_it_means' => $brief['what_it_means'],
            'confidence_level' => 'High',
            'data_sources' => ['RSS feeds', 'Government APIs', 'Market analysis']
        ],
        'recommendations' => $brief['next_actions']
    ],
    'metadata' => [
        'generated_at' => date('c'),
        'coverage_period' => '24 hours',
        'opportunities_count' => count($brief['opportunities']),
        'total_value' => array_sum(array_map(function($opp) {
            return floatval(str_replace(['$', 'M', 'K'], ['', '000000', '000'], $opp['value']));
        }, $brief['opportunities']))
    ],
    'actions' => [
        [
            'label' => 'Export Brief',
            'action' => 'export_brief',
            'data' => ['format' => 'pdf']
        ],
        [
            'label' => 'Schedule Review',
            'action' => 'schedule_meeting',
            'data' => ['title' => 'Brief Review Meeting']
        ]
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>