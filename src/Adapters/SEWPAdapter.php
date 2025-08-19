<?php
/**
 * SEWP (NASA) Program Adapter
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

namespace SamFedBiz\Adapters;

class SEWPAdapter implements ProgramAdapterInterface
{
    private const SEWP_GROUPS = [
        'A' => 'Group A - Hardware',
        'B' => 'Group B - Software',
        'C' => 'Group C - Services'
    ];

    public function code(): string
    {
        return 'sewp';
    }

    public function name(): string
    {
        return 'NASA SEWP';
    }

    public function keywords(): array
    {
        return [
            'NASA SEWP',
            'SEWP V',
            'SEWP VI',
            'Solutions for Enterprise-Wide Procurement',
            'GWAC IT',
            'GWAC AV',
            'Information Technology',
            'Audio Visual',
            'Hardware',
            'Software',
            'Services',
            'Group A',
            'Group B',
            'Group C',
            'Ordering Guide',
            'Marketplace',
            'COTS',
            'Tech Refresh'
        ];
    }

    public function listPrimesOrHolders(): array
    {
        // Placeholder for SEWP holders
        // In production, this would be populated from NASA SEWP data
        return [
            [
                'name' => 'Example Tech Solutions',
                'type' => 'holder',
                'group' => 'A',
                'contract_no' => 'NNG15SC01B',
                'naics' => '541511, 541512',
                'psc' => '7030, 7035',
                'status' => 'active'
            ],
            [
                'name' => 'Software Innovations Inc',
                'type' => 'holder',
                'group' => 'B',
                'contract_no' => 'NNG15SC02B',
                'naics' => '541511, 541513',
                'psc' => '7030, 7040',
                'status' => 'active'
            ],
            [
                'name' => 'IT Services Group',
                'type' => 'holder',
                'group' => 'C',
                'contract_no' => 'NNG15SC03B',
                'naics' => '541511, 541519',
                'psc' => '7030, 7045',
                'status' => 'active'
            ]
        ];
    }

    public function fetchSolicitations(): array
    {
        // Placeholder for SEWP solicitation fetching
        return [
            [
                'source_id' => 'SEWP-RFQ-2025-001',
                'title' => 'Enterprise Network Infrastructure',
                'agency' => 'Department of Defense',
                'posted_date' => '2025-08-14',
                'response_date' => '2025-09-14',
                'status' => 'Open',
                'url' => 'https://sewp.nasa.gov/rfq/example1',
                'group' => 'A',
                'naics' => '541511',
                'psc' => '7030'
            ],
            [
                'source_id' => 'SEWP-RFQ-2025-002',
                'title' => 'Cloud Software Licensing',
                'agency' => 'Department of Education',
                'posted_date' => '2025-08-11',
                'response_date' => '2025-09-11',
                'status' => 'Open',
                'url' => 'https://sewp.nasa.gov/rfq/example2',
                'group' => 'B',
                'naics' => '541513',
                'psc' => '7040'
            ]
        ];
    }

    public function normalize(array $raw): array
    {
        return [
            'opp_no' => $raw['source_id'] ?? '',
            'title' => $raw['title'] ?? '',
            'agency' => $raw['agency'] ?? '',
            'status' => strtolower($raw['status'] ?? 'open'),
            'close_date' => $raw['response_date'] ?? null,
            'url' => $raw['url'] ?? '',
            'meta' => [
                'sewp_group' => $raw['group'] ?? '',
                'naics' => $raw['naics'] ?? '',
                'psc' => $raw['psc'] ?? '',
                'posted_date' => $raw['posted_date'] ?? null
            ]
        ];
    }

    public function extraFields(): array
    {
        return [
            'sewp_group' => 'SEWP Group (A/B/C)',
            'contract_no' => 'Contract Number',
            'naics' => 'NAICS Codes',
            'psc' => 'PSC Codes',
            'oem_authorizations' => 'OEM Authorizations',
            'ordering_guide' => 'Ordering Guide Links'
        ];
    }
}