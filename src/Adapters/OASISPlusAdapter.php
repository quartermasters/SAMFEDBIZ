<?php
/**
 * OASIS+ Program Adapter
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

namespace SamFedBiz\Adapters;

class OASISPlusAdapter implements ProgramAdapterInterface
{
    private const OASIS_POOLS = [
        'SB' => 'Small Business',
        'UR' => 'Unrestricted'
    ];

    private const OASIS_DOMAINS = [
        'Business' => 'Business and Executive',
        'Technical' => 'Technical',
        'Management' => 'Management and Advisory',
        'Construction' => 'Construction'
    ];

    public function code(): string
    {
        return 'oasisplus';
    }

    public function name(): string
    {
        return 'OASIS+';
    }

    public function keywords(): array
    {
        return [
            'OASIS+',
            'OASIS Plus',
            'GSA GWAC',
            'Government-wide Acquisition Contract',
            'Professional Services',
            'Small Business',
            'Unrestricted',
            'Pool 1',
            'Pool 2',
            'Business Domain',
            'Technical Domain',
            'Management Advisory',
            'Construction'
        ];
    }

    public function listPrimesOrHolders(): array
    {
        // Placeholder for OASIS+ holders
        // In production, this would be populated from GSA data
        return [
            [
                'name' => 'Example Contractor 1',
                'type' => 'holder',
                'pool' => 'SB',
                'domains' => ['Business', 'Technical'],
                'status' => 'active',
                'contract_no' => 'GS00Q17GWD2001'
            ],
            [
                'name' => 'Example Contractor 2',
                'type' => 'holder',
                'pool' => 'UR',
                'domains' => ['Technical', 'Management'],
                'status' => 'active',
                'contract_no' => 'GS00Q17GWD2002'
            ]
        ];
    }

    public function fetchSolicitations(): array
    {
        // Placeholder for OASIS+ solicitation fetching
        return [
            [
                'source_id' => 'OASIS-SB-2025-001',
                'title' => 'Cybersecurity Professional Services',
                'agency' => 'Department of Defense',
                'posted_date' => '2025-08-12',
                'response_date' => '2025-09-12',
                'status' => 'Open',
                'url' => 'https://sam.gov/opp/oasis-example1',
                'pool' => 'SB',
                'domain' => 'Technical',
                'evaluation_factors' => 'Technical, Past Performance, Price'
            ],
            [
                'source_id' => 'OASIS-UR-2025-002',
                'title' => 'Business Process Improvement',
                'agency' => 'Department of Health and Human Services',
                'posted_date' => '2025-08-08',
                'response_date' => '2025-09-08',
                'status' => 'Open',
                'url' => 'https://sam.gov/opp/oasis-example2',
                'pool' => 'UR',
                'domain' => 'Business',
                'evaluation_factors' => 'Technical, Management, Price'
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
                'pool' => $raw['pool'] ?? '',
                'domain' => $raw['domain'] ?? '',
                'evaluation_factors' => $raw['evaluation_factors'] ?? '',
                'posted_date' => $raw['posted_date'] ?? null
            ]
        ];
    }

    public function extraFields(): array
    {
        return [
            'pool' => 'OASIS+ Pool (SB/UR)',
            'domain' => 'Service Domain',
            'evaluation_factors' => 'Evaluation Factors',
            'contract_no' => 'Contract Number',
            'capability_areas' => 'Capability Areas'
        ];
    }
}