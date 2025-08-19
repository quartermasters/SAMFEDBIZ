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
            'name' => 'ADS',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active'
        ],
        'Federal Resources' => [
            'name' => 'Federal Resources',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active'
        ],
        'Quantico Tactical' => [
            'name' => 'Quantico Tactical',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active'
        ],
        'SupplyCore' => [
            'name' => 'SupplyCore',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active'
        ],
        'TSSi' => [
            'name' => 'TSSi',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active'
        ],
        'W.S. Darley & Co' => [
            'name' => 'W.S. Darley & Co',
            'type' => 'prime',
            'scope' => 'SOE/F&ESE',
            'status' => 'active'
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
            'kit_support' => 'Kit Support Available'
        ];
    }
}