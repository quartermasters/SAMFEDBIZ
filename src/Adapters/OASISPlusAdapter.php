<?php
/**
 * OASIS+ Adapter
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles OASIS+ contract holders, pools, and domains
 */

namespace SamFedBiz\Adapters;

use SamFedBiz\Adapters\ProgramAdapterInterface;
use PDO;
use Exception;

class OASISPlusAdapter implements ProgramAdapterInterface
{
    private $pdo;
    
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Get program code
     */
    public function code(): string
    {
        return 'oasis+';
    }
    
    /**
     * Get program name
     */
    public function name(): string
    {
        return 'OASIS+ (Office of Administrative Services Indefinite Delivery/Indefinite Quantity)';
    }
    
    /**
     * Get OASIS+ specific keywords for news scanning
     */
    public function keywords(): array
    {
        return [
            'OASIS+', 'OASIS Plus', 'Office of Administrative Services',
            'IDIQ', 'Indefinite Delivery Indefinite Quantity',
            'Task Order', 'TO', 'Professional Services',
            'Management Consulting', 'Technical Services',
            'Engineering Services', 'Administrative Support',
            'Program Management Office', 'PMO',
            'Systems Engineering', 'IT Consulting',
            'Business Process Improvement', 'Training Services',
            'Cybersecurity Services', 'Data Analytics',
            'GSA OASIS', 'Pool SB', 'Pool UR',
            'Small Business Pool', 'Unrestricted Pool',
            'Domain 1', 'Domain 2', 'Domain 3', 'Domain 4', 'Domain 5', 'Domain 6',
            'Management and Advisory', 'Technical and Engineering',
            'Logistics Services', 'Research and Development',
            'Intelligence Services', 'Environmental Services'
        ];
    }
    
    /**
     * List OASIS+ contract holders with pool and domain information
     */
    public function listPrimesOrHolders(): array
    {
        if (!$this->pdo) {
            return $this->getStaticHolders();
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT h.*, hm.pool, hm.domains, hm.contract_number, 
                       hm.naics_codes, hm.capabilities, hm.past_performance
                FROM holders h
                LEFT JOIN holder_meta hm ON h.id = hm.holder_id
                WHERE h.program_code = 'oasis+'
                ORDER BY h.name
            ");
            $stmt->execute();
            $holders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($holders as &$holder) {
                $holder['domains'] = json_decode($holder['domains'] ?? '[]', true);
                $holder['naics_codes'] = json_decode($holder['naics_codes'] ?? '[]', true);
                $holder['capabilities'] = json_decode($holder['capabilities'] ?? '[]', true);
                $holder['past_performance'] = json_decode($holder['past_performance'] ?? '[]', true);
            }
            
            return $holders;
        } catch (Exception $e) {
            error_log("OASIS+ adapter database error: " . $e->getMessage());
            return $this->getStaticHolders();
        }
    }
    
    /**
     * Get static OASIS+ holders for fallback
     */
    private function getStaticHolders(): array
    {
        return [
            [
                'id' => 'oasis_accenture',
                'name' => 'Accenture Federal Services',
                'full_name' => 'Accenture Federal Services LLC',
                'pool' => 'UR',
                'domains' => [1, 2, 3, 4, 5, 6],
                'contract_number' => '47QRAA18D003F',
                'naics_codes' => ['541611', '541512', '541330'],
                'capabilities' => [
                    'management_consulting',
                    'technology_consulting',
                    'digital_transformation',
                    'cybersecurity',
                    'data_analytics',
                    'cloud_services'
                ],
                'past_performance' => [
                    'scope' => 'Large-scale digital transformation projects',
                    'value' => '> $100M annually',
                    'rating' => 'Exceptional'
                ]
            ],
            [
                'id' => 'oasis_booz_allen',
                'name' => 'Booz Allen Hamilton',
                'full_name' => 'Booz Allen Hamilton Inc.',
                'pool' => 'UR',
                'domains' => [1, 2, 3, 4, 5],
                'contract_number' => '47QRAA18D003G',
                'naics_codes' => ['541611', '541512', '541715'],
                'capabilities' => [
                    'management_consulting',
                    'systems_engineering',
                    'cybersecurity',
                    'intelligence_services',
                    'data_science'
                ],
                'past_performance' => [
                    'scope' => 'Intelligence and defense consulting',
                    'value' => '> $100M annually',
                    'rating' => 'Exceptional'
                ]
            ],
            [
                'id' => 'oasis_caci',
                'name' => 'CACI International',
                'full_name' => 'CACI International Inc.',
                'pool' => 'UR',
                'domains' => [2, 3, 4, 5],
                'contract_number' => '47QRAA18D003H',
                'naics_codes' => ['541512', '541330', '541715'],
                'capabilities' => [
                    'intelligence_services',
                    'cybersecurity',
                    'systems_engineering',
                    'software_development',
                    'mission_support'
                ],
                'past_performance' => [
                    'scope' => 'Intelligence and national security',
                    'value' => '$50M - $100M annually',
                    'rating' => 'Very Good'
                ]
            ],
            [
                'id' => 'oasis_general_dynamics',
                'name' => 'General Dynamics IT',
                'full_name' => 'General Dynamics Information Technology Inc.',
                'pool' => 'UR',
                'domains' => [2, 3, 4, 5, 6],
                'contract_number' => '47QRAA18D003I',
                'naics_codes' => ['541512', '541330', '541715'],
                'capabilities' => [
                    'systems_engineering',
                    'cybersecurity',
                    'cloud_services',
                    'managed_services',
                    'enterprise_infrastructure'
                ],
                'past_performance' => [
                    'scope' => 'Enterprise IT and cybersecurity',
                    'value' => '> $100M annually',
                    'rating' => 'Exceptional'
                ]
            ],
            [
                'id' => 'oasis_deloitte',
                'name' => 'Deloitte Consulting',
                'full_name' => 'Deloitte Consulting LLP',
                'pool' => 'UR',
                'domains' => [1, 2, 3, 4],
                'contract_number' => '47QRAA18D003J',
                'naics_codes' => ['541611', '541512', '541330'],
                'capabilities' => [
                    'management_consulting',
                    'technology_consulting',
                    'digital_transformation',
                    'process_improvement',
                    'change_management'
                ],
                'past_performance' => [
                    'scope' => 'Management and technology consulting',
                    'value' => '> $100M annually',
                    'rating' => 'Exceptional'
                ]
            ],
            [
                'id' => 'oasis_saic',
                'name' => 'SAIC',
                'full_name' => 'Science Applications International Corporation',
                'pool' => 'UR',
                'domains' => [2, 3, 4, 5, 6],
                'contract_number' => '47QRAA18D003K',
                'naics_codes' => ['541512', '541330', '541715'],
                'capabilities' => [
                    'systems_engineering',
                    'cybersecurity',
                    'mission_support',
                    'research_development',
                    'intelligence_services'
                ],
                'past_performance' => [
                    'scope' => 'Defense and intelligence systems',
                    'value' => '$50M - $100M annually',
                    'rating' => 'Very Good'
                ]
            ],
            // Small Business Pool examples
            [
                'id' => 'oasis_acentek',
                'name' => 'Acentek Inc.',
                'full_name' => 'Acentek Inc.',
                'pool' => 'SB',
                'domains' => [2, 3],
                'contract_number' => '47QRAA18D001A',
                'naics_codes' => ['541512', '541330'],
                'capabilities' => [
                    'software_development',
                    'systems_integration',
                    'data_analytics',
                    'cybersecurity'
                ],
                'past_performance' => [
                    'scope' => 'Custom software and data solutions',
                    'value' => '$10M - $25M annually',
                    'rating' => 'Very Good'
                ]
            ],
            [
                'id' => 'oasis_alion',
                'name' => 'Alion Science',
                'full_name' => 'Alion Science and Technology Corporation',
                'pool' => 'SB',
                'domains' => [2, 4, 5, 6],
                'contract_number' => '47QRAA18D001B',
                'naics_codes' => ['541512', '541715', '541330'],
                'capabilities' => [
                    'research_development',
                    'systems_engineering',
                    'modeling_simulation',
                    'technical_analysis'
                ],
                'past_performance' => [
                    'scope' => 'R&D and technical services',
                    'value' => '$25M - $50M annually',
                    'rating' => 'Very Good'
                ]
            ],
            [
                'id' => 'oasis_smartronix',
                'name' => 'Smartronix Inc.',
                'full_name' => 'Smartronix Inc.',
                'pool' => 'SB',
                'domains' => [2, 3, 4],
                'contract_number' => '47QRAA18D001C',
                'naics_codes' => ['541512', '541330'],
                'capabilities' => [
                    'cloud_services',
                    'cybersecurity',
                    'data_analytics',
                    'application_development'
                ],
                'past_performance' => [
                    'scope' => 'Cloud and cybersecurity solutions',
                    'value' => '$10M - $25M annually',
                    'rating' => 'Good'
                ]
            ]
        ];
    }
    
    /**
     * Fetch OASIS+ solicitations (task orders)
     */
    public function fetchSolicitations(): array
    {
        // Simulate OASIS+ task orders
        return [
            [
                'id' => 'oasis_to_001',
                'opp_no' => '47QRAA20F0001',
                'title' => 'Digital Transformation Services - Task Order',
                'agency' => 'General Services Administration',
                'description' => 'Comprehensive digital transformation services including cloud migration, process automation, and change management support.',
                'status' => 'open',
                'type' => 'Task Order',
                'close_date' => date('Y-m-d', strtotime('+21 days')),
                'pool_requirement' => 'UR',
                'domain_requirement' => [1, 2],
                'estimated_value' => '$15,000,000',
                'period_of_performance' => '3 years',
                'url' => 'https://sam.gov/opp/47QRAA20F0001',
                'naics_codes' => ['541611', '541512'],
                'small_business_setaside' => false
            ],
            [
                'id' => 'oasis_to_002',
                'opp_no' => '47QRAA20F0002',
                'title' => 'Cybersecurity Assessment and Implementation',
                'agency' => 'Department of Homeland Security',
                'description' => 'Cybersecurity risk assessment, implementation of security controls, and ongoing monitoring services.',
                'status' => 'open',
                'type' => 'Task Order',
                'close_date' => date('Y-m-d', strtotime('+14 days')),
                'pool_requirement' => 'SB',
                'domain_requirement' => [3, 4],
                'estimated_value' => '$8,500,000',
                'period_of_performance' => '2 years',
                'url' => 'https://sam.gov/opp/47QRAA20F0002',
                'naics_codes' => ['541512', '541330'],
                'small_business_setaside' => true
            ],
            [
                'id' => 'oasis_to_003',
                'opp_no' => '47QRAA20F0003',
                'title' => 'Data Analytics and Business Intelligence Platform',
                'agency' => 'Department of Veterans Affairs',
                'description' => 'Development and implementation of enterprise data analytics platform with business intelligence capabilities.',
                'status' => 'open',
                'type' => 'Task Order',
                'close_date' => date('Y-m-d', strtotime('+28 days')),
                'pool_requirement' => 'UR',
                'domain_requirement' => [2, 3],
                'estimated_value' => '$22,000,000',
                'period_of_performance' => '4 years',
                'url' => 'https://sam.gov/opp/47QRAA20F0003',
                'naics_codes' => ['541512', '541330'],
                'small_business_setaside' => false
            ]
        ];
    }
    
    /**
     * Normalize solicitation data
     */
    public function normalize(array $solicitation): array
    {
        return [
            'opp_no' => $solicitation['opp_no'] ?? '',
            'title' => $solicitation['title'] ?? '',
            'agency' => $solicitation['agency'] ?? '',
            'status' => $solicitation['status'] ?? 'unknown',
            'close_date' => $solicitation['close_date'] ?? '',
            'url' => $solicitation['url'] ?? ''
        ];
    }
    
    /**
     * Get OASIS+ specific fields for opportunities
     */
    public function extraFields(): array
    {
        return [
            'pool_requirement' => 'Pool Requirement',
            'domain_requirement' => 'Domain Requirement',
            'estimated_value' => 'Estimated Value',
            'period_of_performance' => 'Period of Performance',
            'naics_codes' => 'NAICS Codes',
            'small_business_setaside' => 'Small Business Set-Aside'
        ];
    }
    
    /**
     * Get domain information
     */
    public function getDomains(): array
    {
        return [
            1 => [
                'name' => 'Management and Advisory',
                'description' => 'Management consulting, strategic planning, organizational development, and advisory services',
                'naics_primary' => '541611'
            ],
            2 => [
                'name' => 'Technical and Engineering',
                'description' => 'Engineering services, technical analysis, systems engineering, and architecture',
                'naics_primary' => '541330'
            ],
            3 => [
                'name' => 'Information Technology',
                'description' => 'IT consulting, software development, systems integration, and technology services',
                'naics_primary' => '541512'
            ],
            4 => [
                'name' => 'Logistics Services',
                'description' => 'Supply chain management, logistics planning, and material management services',
                'naics_primary' => '541614'
            ],
            5 => [
                'name' => 'Research and Development',
                'description' => 'Scientific research, product development, and innovation services',
                'naics_primary' => '541715'
            ],
            6 => [
                'name' => 'Intelligence Services',
                'description' => 'Intelligence analysis, security services, and specialized advisory support',
                'naics_primary' => '561210'
            ]
        ];
    }
    
    /**
     * Get pools information
     */
    public function getPools(): array
    {
        return [
            'SB' => [
                'name' => 'Small Business',
                'description' => 'Small business set-aside pool for companies meeting SBA size standards',
                'ceiling' => '$15B over 10 years'
            ],
            'UR' => [
                'name' => 'Unrestricted',
                'description' => 'Full and open competition pool for all eligible contractors',
                'ceiling' => '$60B over 10 years'
            ]
        ];
    }
    
    /**
     * Get holder by ID with enhanced OASIS+ data
     */
    public function getHolderById(string $holderId): ?array
    {
        $holders = $this->listPrimesOrHolders();
        
        foreach ($holders as $holder) {
            if ($holder['id'] === $holderId) {
                // Add domain details
                $holder['domain_details'] = [];
                $domains = $this->getDomains();
                foreach ($holder['domains'] as $domainNum) {
                    if (isset($domains[$domainNum])) {
                        $holder['domain_details'][$domainNum] = $domains[$domainNum];
                    }
                }
                
                // Add pool details
                $pools = $this->getPools();
                $holder['pool_details'] = $pools[$holder['pool']] ?? null;
                
                return $holder;
            }
        }
        
        return null;
    }
    
    /**
     * Generate capability sheet for OASIS+ holder
     */
    public function generateCapabilitySheet(string $holderId): ?array
    {
        $holder = $this->getHolderById($holderId);
        if (!$holder) {
            return null;
        }
        
        return [
            'holder_name' => $holder['name'],
            'pool' => $holder['pool'],
            'domains' => $holder['domains'],
            'domain_details' => $holder['domain_details'],
            'contract_number' => $holder['contract_number'],
            'naics_codes' => $holder['naics_codes'],
            'capabilities' => $holder['capabilities'],
            'past_performance' => $holder['past_performance'],
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
}
