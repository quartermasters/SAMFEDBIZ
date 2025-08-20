<?php
/**
 * TLS (DLA SOE/F&ESE) Program Adapter
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

namespace SamFedBiz\Adapters;

class TLSAdapter implements ProgramAdapterInterface
{
    private const EXCLUDED_PRIMES = ['Noble Supply & Logistics'];
    
    private const TLS_PRIMES = [
        'ADS' => [
            'id' => 'ads',
            'name' => 'ADS',
            'full_name' => 'American Defense Systems',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active',
            'contract_number' => 'TLS-ADS-2024',
            'capabilities' => ['tactical_gear', 'protective_equipment', 'field_equipment'],
            'contact_email' => 'bd@ads-usa.com',
            'phone' => '+1-703-555-0101',
            'website' => 'https://ads-usa.com',
            'established' => '1985',
            'primary_naics' => ['336411', '339999'],
            'kit_support' => true,
            'lead_time_days' => 30
        ],
        'Federal Resources' => [
            'id' => 'federal_resources',
            'name' => 'Federal Resources',
            'full_name' => 'Federal Resources Corporation',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active',
            'contract_number' => 'TLS-FR-2024',
            'capabilities' => ['logistics_support', 'supply_chain', 'warehousing'],
            'contact_email' => 'contracts@federalresources.com',
            'phone' => '+1-703-555-0102',
            'website' => 'https://federalresources.com',
            'established' => '1992',
            'primary_naics' => ['488510', '493110'],
            'kit_support' => true,
            'lead_time_days' => 45
        ],
        'Quantico Tactical' => [
            'id' => 'quantico_tactical',
            'name' => 'Quantico Tactical',
            'full_name' => 'Quantico Tactical Solutions LLC',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active',
            'contract_number' => 'TLS-QT-2024',
            'capabilities' => ['training_equipment', 'simulation_systems', 'tactical_solutions'],
            'contact_email' => 'sales@quanticotactical.com',
            'phone' => '+1-703-555-0103',
            'website' => 'https://quanticotactical.com',
            'established' => '2008',
            'primary_naics' => ['334511', '339999'],
            'kit_support' => false,
            'lead_time_days' => 60
        ],
        'SupplyCore' => [
            'id' => 'supplycore',
            'name' => 'SupplyCore',
            'full_name' => 'SupplyCore Solutions Inc.',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active',
            'contract_number' => 'TLS-SC-2024',
            'capabilities' => ['inventory_management', 'procurement', 'distribution'],
            'contact_email' => 'info@supplycore.com',
            'phone' => '+1-703-555-0104',
            'website' => 'https://supplycore.com',
            'established' => '2001',
            'primary_naics' => ['541614', '493110'],
            'kit_support' => true,
            'lead_time_days' => 21
        ],
        'TSSi' => [
            'id' => 'tssi',
            'name' => 'TSSi',
            'full_name' => 'Tactical Support Systems International',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active',
            'contract_number' => 'TLS-TSSI-2024',
            'capabilities' => ['field_communications', 'support_systems', 'maintenance'],
            'contact_email' => 'business@tssi-tactical.com',
            'phone' => '+1-703-555-0105',
            'website' => 'https://tssi-tactical.com',
            'established' => '1998',
            'primary_naics' => ['334220', '811219'],
            'kit_support' => false,
            'lead_time_days' => 35
        ],
        'W.S. Darley & Co' => [
            'id' => 'ws_darley',
            'name' => 'W.S. Darley & Co',
            'full_name' => 'W.S. Darley & Company',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active',
            'contract_number' => 'TLS-WSD-2024',
            'capabilities' => ['emergency_equipment', 'rescue_gear', 'safety_systems'],
            'contact_email' => 'federal@darley.com',
            'phone' => '+1-630-555-0106',
            'website' => 'https://darley.com',
            'established' => '1908',
            'primary_naics' => ['336214', '333924'],
            'kit_support' => true,
            'lead_time_days' => 42
        ]
    ];

    public function code(): string
    {
        return 'tls';
    }

    public function name(): string
    {
        return 'DLA TLS (SOE/F&ESE)';
    }

    public function keywords(): array
    {
        return [
            'DLA TLS',
            'SOE',
            'F&ESE',
            'Special Operations Equipment',
            'Fire & Emergency Services Equipment',
            'ADS',
            'Federal Resources',
            'Quantico Tactical',
            'SupplyCore',
            'TSSi',
            'W.S. Darley',
            'Defense Logistics Agency',
            'Tailored Logistics Support'
        ];
    }

    public function listPrimesOrHolders(): array
    {
        // Filter out excluded primes per internal policy
        return array_filter(self::TLS_PRIMES, function($prime) {
            return !in_array($prime['name'], self::EXCLUDED_PRIMES);
        });
    }

    public function fetchSolicitations(): array
    {
        // Placeholder for TLS solicitation fetching
        // In production, this would connect to SAM.gov or other sources
        return [
            [
                'source_id' => 'TLS-SOE-2025-001',
                'title' => 'Special Operations Equipment - Tactical Gear',
                'agency' => 'DLA',
                'posted_date' => '2025-08-15',
                'response_date' => '2025-09-15',
                'status' => 'Open',
                'url' => 'https://sam.gov/opp/example1',
                'scope' => 'SOE',
                'set_aside' => 'Small Business'
            ],
            [
                'source_id' => 'TLS-FESE-2025-002',
                'title' => 'Fire & Emergency Services Equipment',
                'agency' => 'DLA',
                'posted_date' => '2025-08-10',
                'response_date' => '2025-09-10',
                'status' => 'Open',
                'url' => 'https://sam.gov/opp/example2',
                'scope' => 'F&ESE',
                'set_aside' => 'Unrestricted'
            ]
        ];
    }

    public function normalize(array $raw): array
    {
        return [
            'opp_no' => $raw['source_id'] ?? '',
            'title' => $raw['title'] ?? '',
            'agency' => $raw['agency'] ?? 'DLA',
            'status' => strtolower($raw['status'] ?? 'open'),
            'close_date' => $raw['response_date'] ?? null,
            'url' => $raw['url'] ?? '',
            'meta' => [
                'scope' => $raw['scope'] ?? '',
                'set_aside' => $raw['set_aside'] ?? '',
                'posted_date' => $raw['posted_date'] ?? null
            ]
        ];
    }

    public function extraFields(): array
    {
        return [
            'tls_scope' => 'TLS Scope (SOE/F&ESE)',
            'set_aside' => 'Set-Aside Type',
            'posted_date' => 'Posted Date',
            'prime_awards' => 'Prime Awards',
            'kit_support' => 'Kit Support Available',
            'lead_time_days' => 'Lead Time (Days)',
            'contract_number' => 'Contract Number',
            'primary_naics' => 'Primary NAICS Codes'
        ];
    }

    /**
     * Get prime contractor by ID
     * @param string $primeId
     * @return array|null
     */
    public function getPrimeById(string $primeId): ?array
    {
        foreach (self::TLS_PRIMES as $prime) {
            if ($prime['id'] === $primeId) {
                return $prime;
            }
        }
        return null;
    }

    /**
     * Get primes that support kit building
     * @return array
     */
    public function getKitSupportPrimes(): array
    {
        return array_filter(self::TLS_PRIMES, function($prime) {
            return $prime['kit_support'] === true;
        });
    }

    /**
     * Get primes by capability
     * @param string $capability
     * @return array
     */
    public function searchByCapability(string $capability): array
    {
        return array_filter(self::TLS_PRIMES, function($prime) use ($capability) {
            return in_array($capability, $prime['capabilities']);
        });
    }

    /**
     * Get average lead time for primes
     * @return int
     */
    public function getAverageLeadTime(): int
    {
        $leadTimes = array_column(self::TLS_PRIMES, 'lead_time_days');
        return (int) round(array_sum($leadTimes) / count($leadTimes));
    }

    /**
     * Get primes sorted by lead time
     * @param bool $ascending
     * @return array
     */
    public function getPrimesByLeadTime(bool $ascending = true): array
    {
        $primes = self::TLS_PRIMES;
        uasort($primes, function($a, $b) use ($ascending) {
            return $ascending ? 
                $a['lead_time_days'] <=> $b['lead_time_days'] :
                $b['lead_time_days'] <=> $a['lead_time_days'];
        });
        return $primes;
    }

    /**
     * Generate micro-catalog data for a prime
     * @param string $primeId
     * @return array
     */
    public function generateMicroCatalog(string $primeId): array
    {
        $prime = $this->getPrimeById($primeId);
        if (!$prime) {
            return [];
        }

        return [
            'prime_info' => $prime,
            'catalog_sections' => $this->getCatalogSections($prime),
            'capabilities_matrix' => $this->getCapabilitiesMatrix($prime),
            'contact_info' => [
                'email' => $prime['contact_email'],
                'phone' => $prime['phone'],
                'website' => $prime['website']
            ],
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => 'samfedbiz.com'
        ];
    }

    /**
     * Get catalog sections for a prime
     * @param array $prime
     * @return array
     */
    private function getCatalogSections(array $prime): array
    {
        $sections = [];
        foreach ($prime['capabilities'] as $capability) {
            $sections[] = [
                'capability' => $capability,
                'title' => ucwords(str_replace('_', ' ', $capability)),
                'description' => $this->getCapabilityDescription($capability),
                'example_parts' => $this->getExampleParts($capability),
                'use_cases' => $this->getUseCases($capability)
            ];
        }
        return $sections;
    }

    /**
     * Get capabilities matrix for a prime
     * @param array $prime
     * @return array
     */
    private function getCapabilitiesMatrix(array $prime): array
    {
        $matrix = [];
        foreach ($prime['capabilities'] as $capability) {
            $matrix[$capability] = [
                'available' => true,
                'lead_time' => $prime['lead_time_days'],
                'kit_support' => $prime['kit_support'],
                'naics_codes' => $prime['primary_naics']
            ];
        }
        return $matrix;
    }

    /**
     * Get capability description
     * @param string $capability
     * @return string
     */
    private function getCapabilityDescription(string $capability): string
    {
        $descriptions = [
            'tactical_gear' => 'High-performance tactical equipment for military and law enforcement operations',
            'protective_equipment' => 'Personal protective equipment and safety gear for tactical environments',
            'field_equipment' => 'Portable equipment designed for field operations and deployment',
            'logistics_support' => 'Comprehensive logistics and supply chain management services',
            'supply_chain' => 'End-to-end supply chain solutions and procurement services',
            'warehousing' => 'Secure storage and distribution facility management',
            'training_equipment' => 'Equipment and systems for tactical training and simulation',
            'simulation_systems' => 'Advanced simulation and training technology platforms',
            'tactical_solutions' => 'Integrated tactical solutions and mission support',
            'inventory_management' => 'Advanced inventory tracking and management systems',
            'procurement' => 'Strategic procurement and vendor management services',
            'distribution' => 'Efficient distribution networks and delivery systems',
            'field_communications' => 'Tactical communication systems for field operations',
            'support_systems' => 'Technical support and maintenance systems',
            'maintenance' => 'Equipment maintenance and repair services',
            'emergency_equipment' => 'Emergency response and rescue equipment',
            'rescue_gear' => 'Specialized rescue and recovery equipment',
            'safety_systems' => 'Comprehensive safety and protection systems'
        ];
        
        return $descriptions[$capability] ?? 'Specialized equipment and services';
    }

    /**
     * Get example parts for a capability
     * @param string $capability
     * @return array
     */
    private function getExampleParts(string $capability): array
    {
        $parts = [
            'tactical_gear' => ['TG-001', 'TG-025', 'TG-047'],
            'protective_equipment' => ['PE-100', 'PE-200', 'PE-300'],
            'field_equipment' => ['FE-500', 'FE-750', 'FE-900'],
            'logistics_support' => ['LS-001', 'LS-002', 'LS-003'],
            'emergency_equipment' => ['EE-100', 'EE-200', 'EE-300']
        ];
        
        return $parts[$capability] ?? ['MISC-001', 'MISC-002', 'MISC-003'];
    }

    /**
     * Get use cases for a capability
     * @param string $capability
     * @return array
     */
    private function getUseCases(string $capability): array
    {
        $useCases = [
            'tactical_gear' => ['Combat Operations', 'Training Exercises', 'Field Deployment'],
            'protective_equipment' => ['Personal Protection', 'Hazmat Response', 'Security Operations'],
            'field_equipment' => ['Mobile Operations', 'Remote Deployment', 'Temporary Installations'],
            'logistics_support' => ['Supply Chain Management', 'Inventory Control', 'Distribution Planning'],
            'emergency_equipment' => ['Emergency Response', 'Disaster Relief', 'Rescue Operations']
        ];
        
        return $useCases[$capability] ?? ['General Purpose', 'Mission Support', 'Operational Use'];
    }
}