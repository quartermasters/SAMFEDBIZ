<?php
/**
 * SEWP Adapter
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles NASA SEWP contract holders, groups, and ordering guides
 */

namespace SamFedBiz\Adapters;

use SamFedBiz\Adapters\ProgramAdapterInterface;
use PDO;
use Exception;

class SEWPAdapter implements ProgramAdapterInterface
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
        return 'sewp';
    }
    
    /**
     * Get program name
     */
    public function name(): string
    {
        return 'NASA SEWP VI (Solutions for Enterprise-Wide Procurement)';
    }
    
    /**
     * Get SEWP specific keywords for news scanning
     */
    public function keywords(): array
    {
        return [
            'NASA SEWP', 'SEWP V', 'SEWP VI', 'SEWP 6',
            'Solutions for Enterprise-Wide Procurement',
            'GWAC IT', 'GWAC AV', 'Government-wide Acquisition Contract',
            'Information Technology', 'Audio Visual',
            'Hardware', 'Software', 'Services',
            'Group A', 'Group B', 'Group C',
            'Ordering Guide', 'Marketplace', 'E-Marketplace',
            'COTS', 'Commercial Off-the-Shelf',
            'Tech Refresh', 'Technology Refresh',
            'IT Hardware', 'Enterprise Software',
            'Cloud Services', 'Cybersecurity',
            'Data Center', 'Network Infrastructure',
            'End User Devices', 'Telecommunications',
            'Audio Visual Systems', 'Digital Signage',
            'Video Conferencing', 'Collaboration Tools',
            'OEM Authorization', 'Reseller Agreement',
            'NASA Goddard', 'GSFC'
        ];
    }
    
    /**
     * List SEWP contract holders with group and contract information
     */
    public function listPrimesOrHolders(): array
    {
        if (!$this->pdo) {
            return $this->getStaticHolders();
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT h.*, hm.sewp_group, hm.contract_number, hm.naics_codes, 
                       hm.psc_codes, hm.oem_authorizations, hm.capabilities
                FROM holders h
                LEFT JOIN holder_meta hm ON h.id = hm.holder_id
                WHERE h.program_code = 'sewp'
                ORDER BY h.name
            ");
            $stmt->execute();
            $holders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($holders as &$holder) {
                $holder['naics_codes'] = json_decode($holder['naics_codes'] ?? '[]', true);
                $holder['psc_codes'] = json_decode($holder['psc_codes'] ?? '[]', true);
                $holder['oem_authorizations'] = json_decode($holder['oem_authorizations'] ?? '[]', true);
                $holder['capabilities'] = json_decode($holder['capabilities'] ?? '[]', true);
            }
            
            return $holders;
        } catch (Exception $e) {
            error_log("SEWP adapter database error: " . $e->getMessage());
            return $this->getStaticHolders();
        }
    }
    
    /**
     * Get static SEWP holders for fallback
     */
    private function getStaticHolders(): array
    {
        return [
            // Group A - Hardware
            [
                'id' => 'sewp_dell',
                'name' => 'Dell Technologies',
                'full_name' => 'Dell Technologies Federal Systems',
                'sewp_group' => 'A',
                'contract_number' => 'NNG15SC01B',
                'naics_codes' => ['334111', '423430', '541511'],
                'psc_codes' => ['7021', '7025', '7030', '7035'],
                'oem_authorizations' => ['Dell', 'EMC', 'VMware', 'Pivotal'],
                'capabilities' => [
                    'enterprise_servers',
                    'storage_solutions',
                    'networking_equipment',
                    'end_user_devices',
                    'virtualization',
                    'cloud_infrastructure'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/dell'
            ],
            [
                'id' => 'sewp_hp',
                'name' => 'HP Inc.',
                'full_name' => 'HP Inc. Federal',
                'sewp_group' => 'A',
                'contract_number' => 'NNG15SC02B',
                'naics_codes' => ['334111', '423430', '541511'],
                'psc_codes' => ['7021', '7025', '7030'],
                'oem_authorizations' => ['HP', 'HPE', 'Aruba', 'Poly'],
                'capabilities' => [
                    'workstations',
                    'printers',
                    'networking_equipment',
                    'end_user_devices',
                    'enterprise_servers'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/hp'
            ],
            [
                'id' => 'sewp_lenovo',
                'name' => 'Lenovo',
                'full_name' => 'Lenovo (United States) Inc.',
                'sewp_group' => 'A',
                'contract_number' => 'NNG15SC03B',
                'naics_codes' => ['334111', '423430'],
                'psc_codes' => ['7021', '7025', '7030'],
                'oem_authorizations' => ['Lenovo', 'ThinkSystem', 'ThinkPad'],
                'capabilities' => [
                    'laptops',
                    'desktops',
                    'tablets',
                    'enterprise_servers',
                    'storage_solutions'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/lenovo'
            ],
            [
                'id' => 'sewp_cisco',
                'name' => 'Cisco Systems',
                'full_name' => 'Cisco Systems Inc.',
                'sewp_group' => 'A',
                'contract_number' => 'NNG15SC04B',
                'naics_codes' => ['334210', '423430', '541511'],
                'psc_codes' => ['5820', '5895', '7030'],
                'oem_authorizations' => ['Cisco', 'Meraki', 'Webex', 'Umbrella'],
                'capabilities' => [
                    'networking_equipment',
                    'security_appliances',
                    'collaboration_tools',
                    'cloud_services',
                    'wireless_solutions'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/cisco'
            ],
            // Group B - Software
            [
                'id' => 'sewp_microsoft',
                'name' => 'Microsoft Corporation',
                'full_name' => 'Microsoft Corporation',
                'sewp_group' => 'B',
                'contract_number' => 'NNG15SC11B',
                'naics_codes' => ['511210', '541511', '541512'],
                'psc_codes' => ['7030', '7035', '7040'],
                'oem_authorizations' => ['Microsoft', 'Azure', 'Office 365', 'Dynamics'],
                'capabilities' => [
                    'operating_systems',
                    'productivity_software',
                    'cloud_services',
                    'database_software',
                    'development_tools'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/microsoft'
            ],
            [
                'id' => 'sewp_oracle',
                'name' => 'Oracle Corporation',
                'full_name' => 'Oracle America Inc.',
                'sewp_group' => 'B',
                'contract_number' => 'NNG15SC12B',
                'naics_codes' => ['511210', '541511', '541512'],
                'psc_codes' => ['7030', '7035', '7040'],
                'oem_authorizations' => ['Oracle', 'MySQL', 'Java', 'Solaris'],
                'capabilities' => [
                    'database_software',
                    'enterprise_applications',
                    'cloud_services',
                    'middleware',
                    'analytics_software'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/oracle'
            ],
            [
                'id' => 'sewp_vmware',
                'name' => 'VMware Inc.',
                'full_name' => 'VMware Inc.',
                'sewp_group' => 'B',
                'contract_number' => 'NNG15SC13B',
                'naics_codes' => ['511210', '541511'],
                'psc_codes' => ['7030', '7040'],
                'oem_authorizations' => ['VMware', 'Carbon Black', 'Pivotal'],
                'capabilities' => [
                    'virtualization',
                    'cloud_management',
                    'security_software',
                    'networking_software',
                    'workspace_solutions'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/vmware'
            ],
            // Group C - Services
            [
                'id' => 'sewp_accenture_federal',
                'name' => 'Accenture Federal Services',
                'full_name' => 'Accenture Federal Services LLC',
                'sewp_group' => 'C',
                'contract_number' => 'NNG15SC21B',
                'naics_codes' => ['541511', '541512', '541990'],
                'psc_codes' => ['7030', '7040', 'R425'],
                'oem_authorizations' => ['Multiple Technology Partners'],
                'capabilities' => [
                    'system_integration',
                    'consulting_services',
                    'managed_services',
                    'cloud_migration',
                    'digital_transformation'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/accenture'
            ],
            [
                'id' => 'sewp_cgi_federal',
                'name' => 'CGI Federal Inc.',
                'full_name' => 'CGI Federal Inc.',
                'sewp_group' => 'C',
                'contract_number' => 'NNG15SC22B',
                'naics_codes' => ['541511', '541512', '541519'],
                'psc_codes' => ['7030', '7040', 'R425'],
                'oem_authorizations' => ['Various Technology Partners'],
                'capabilities' => [
                    'it_services',
                    'system_integration',
                    'application_development',
                    'infrastructure_services',
                    'cybersecurity_services'
                ],
                'marketplace_url' => 'https://sewp.nasa.gov/cgi'
            ]
        ];
    }
    
    /**
     * Fetch SEWP solicitations and RFQs
     */
    public function fetchSolicitations(): array
    {
        // Simulate SEWP RFQs and task orders
        return [
            [
                'id' => 'sewp_rfq_001',
                'opp_no' => 'SEWP-RFQ-2025-001',
                'title' => 'Enterprise Network Infrastructure Refresh',
                'agency' => 'Department of Defense',
                'description' => 'Comprehensive network infrastructure upgrade including switches, routers, wireless access points, and security appliances.',
                'status' => 'open',
                'type' => 'Request for Quote',
                'close_date' => date('Y-m-d', strtotime('+21 days')),
                'sewp_group' => 'A',
                'estimated_value' => '$12,000,000',
                'naics_codes' => ['334210', '423430'],
                'psc_codes' => ['5820', '5895'],
                'url' => 'https://sewp.nasa.gov/rfq/001',
                'oem_requirements' => ['Cisco', 'Juniper', 'Aruba']
            ],
            [
                'id' => 'sewp_rfq_002',
                'opp_no' => 'SEWP-RFQ-2025-002',
                'title' => 'Microsoft Enterprise License Agreement',
                'agency' => 'Department of Veterans Affairs',
                'description' => 'Enterprise-wide Microsoft licensing including Office 365, Windows, Azure, and SQL Server for 50,000 users.',
                'status' => 'open',
                'type' => 'Request for Quote',
                'close_date' => date('Y-m-d', strtotime('+14 days')),
                'sewp_group' => 'B',
                'estimated_value' => '$8,500,000',
                'naics_codes' => ['511210'],
                'psc_codes' => ['7030', '7040'],
                'url' => 'https://sewp.nasa.gov/rfq/002',
                'oem_requirements' => ['Microsoft']
            ],
            [
                'id' => 'sewp_rfq_003',
                'opp_no' => 'SEWP-RFQ-2025-003',
                'title' => 'Cloud Migration and Integration Services',
                'agency' => 'General Services Administration',
                'description' => 'Professional services for cloud migration, system integration, and ongoing managed services for hybrid cloud environment.',
                'status' => 'open',
                'type' => 'Request for Quote',
                'close_date' => date('Y-m-d', strtotime('+28 days')),
                'sewp_group' => 'C',
                'estimated_value' => '$15,000,000',
                'naics_codes' => ['541511', '541512'],
                'psc_codes' => ['7030', 'R425'],
                'url' => 'https://sewp.nasa.gov/rfq/003',
                'oem_requirements' => ['AWS', 'Microsoft Azure', 'VMware']
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
     * Get SEWP specific fields for opportunities
     */
    public function extraFields(): array
    {
        return [
            'sewp_group' => 'SEWP Group',
            'contract_number' => 'Contract Number',
            'naics_codes' => 'NAICS Codes',
            'psc_codes' => 'PSC Codes',
            'oem_authorizations' => 'OEM Authorizations',
            'oem_requirements' => 'OEM Requirements',
            'estimated_value' => 'Estimated Value',
            'marketplace_url' => 'Marketplace Link'
        ];
    }
    
    /**
     * Get SEWP groups information
     */
    public function getGroups(): array
    {
        return [
            'A' => [
                'name' => 'Group A - Hardware',
                'description' => 'Information Technology and Audio Visual Hardware including servers, storage, networking, end-user devices, and AV equipment',
                'focus' => 'Hardware Solutions'
            ],
            'B' => [
                'name' => 'Group B - Software',
                'description' => 'Software products including operating systems, applications, databases, security software, and cloud services',
                'focus' => 'Software Solutions'
            ],
            'C' => [
                'name' => 'Group C - Services',
                'description' => 'Information Technology services including system integration, consulting, managed services, and professional services',
                'focus' => 'IT Services'
            ]
        ];
    }
    
    /**
     * Get holder by ID with enhanced SEWP data
     */
    public function getHolderById(string $holderId): ?array
    {
        $holders = $this->listPrimesOrHolders();
        
        foreach ($holders as $holder) {
            if ($holder['id'] === $holderId) {
                // Add group details
                $groups = $this->getGroups();
                $holder['group_details'] = $groups[$holder['sewp_group']] ?? null;
                
                return $holder;
            }
        }
        
        return null;
    }
    
    /**
     * Generate ordering guide for SEWP holder
     */
    public function generateOrderingGuide(string $holderId): ?array
    {
        $holder = $this->getHolderById($holderId);
        if (!$holder) {
            return null;
        }
        
        return [
            'holder_name' => $holder['name'],
            'sewp_group' => $holder['sewp_group'],
            'group_details' => $holder['group_details'],
            'contract_number' => $holder['contract_number'],
            'naics_codes' => $holder['naics_codes'],
            'psc_codes' => $holder['psc_codes'],
            'oem_authorizations' => $holder['oem_authorizations'],
            'capabilities' => $holder['capabilities'],
            'marketplace_url' => $holder['marketplace_url'] ?? null,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get marketplace shortcuts for quick ordering
     */
    public function getMarketplaceShortcuts(): array
    {
        return [
            'hardware' => [
                'name' => 'Hardware Solutions',
                'description' => 'Servers, storage, networking, and end-user devices',
                'url' => 'https://sewp.nasa.gov/hardware',
                'group' => 'A'
            ],
            'software' => [
                'name' => 'Software Solutions',
                'description' => 'Operating systems, applications, and cloud services',
                'url' => 'https://sewp.nasa.gov/software',
                'group' => 'B'
            ],
            'services' => [
                'name' => 'IT Services',
                'description' => 'Professional services and system integration',
                'url' => 'https://sewp.nasa.gov/services',
                'group' => 'C'
            ],
            'audio_visual' => [
                'name' => 'Audio Visual',
                'description' => 'AV equipment, digital signage, and collaboration tools',
                'url' => 'https://sewp.nasa.gov/av',
                'group' => 'A'
            ]
        ];
    }
}
