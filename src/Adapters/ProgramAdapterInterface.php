<?php
/**
 * Program Adapter Interface
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Modular adapter interface for TLS, OASIS+, SEWP programs
 */

namespace SamFedBiz\Adapters;

interface ProgramAdapterInterface
{
    /**
     * Get program code identifier
     * @return string Program code (tls|oasisplus|sewp)
     */
    public function code(): string;

    /**
     * Get human-readable program name
     * @return string Display name for the program
     */
    public function name(): string;

    /**
     * Get keywords for news scanning and brief generation
     * @return array Array of keywords for this program
     */
    public function keywords(): array;

    /**
     * List all primes/holders for this program
     * @return array Structured list of primes/holders with metadata
     */
    public function listPrimesOrHolders(): array;

    /**
     * Fetch raw solicitations from external sources
     * @return array Raw list of opportunities/solicitations
     */
    public function fetchSolicitations(): array;

    /**
     * Normalize raw solicitation data to standard format
     * @param array $raw Raw solicitation data
     * @return array Normalized data with required fields: opp_no, title, agency, status, close_date, url
     */
    public function normalize(array $raw): array;

    /**
     * Get program-specific extra fields for metadata
     * @return array List of additional fields specific to this program
     */
    public function extraFields(): array;
}