<?php
/**
 * Program Registry - Manages program adapters with toggle functionality
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

namespace SamFedBiz\Core;

use SamFedBiz\Adapters\ProgramAdapterInterface;
use SamFedBiz\Adapters\TLSAdapter;
use SamFedBiz\Adapters\OASISPlusAdapter;
use SamFedBiz\Adapters\SEWPAdapter;

class ProgramRegistry
{
    private array $adapters = [];
    private array $activePrograms = [];

    public function __construct()
    {
        $this->registerAdapters();
        $this->loadActivePrograms();
    }

    /**
     * Register all available program adapters
     */
    private function registerAdapters(): void
    {
        $this->adapters = [
            'tls' => new TLSAdapter(),
            'oasisplus' => new OASISPlusAdapter(),
            'sewp' => new SEWPAdapter()
        ];
    }

    /**
     * Load active programs from database
     */
    private function loadActivePrograms(): void
    {
        // In production, this would query the database
        // For now, default all programs to active
        $this->activePrograms = [
            'tls' => true,
            'oasisplus' => true,
            'sewp' => true
        ];
    }

    /**
     * Get adapter by program code
     * @param string $code Program code
     * @return ProgramAdapterInterface|null
     */
    public function getAdapter(string $code): ?ProgramAdapterInterface
    {
        if (!$this->isActive($code)) {
            return null;
        }

        return $this->adapters[$code] ?? null;
    }

    /**
     * Get all active adapters
     * @return array<string, ProgramAdapterInterface>
     */
    public function getActiveAdapters(): array
    {
        $active = [];
        foreach ($this->activePrograms as $code => $isActive) {
            if ($isActive && isset($this->adapters[$code])) {
                $active[$code] = $this->adapters[$code];
            }
        }
        return $active;
    }

    /**
     * Check if program is active
     * @param string $code Program code
     * @return bool
     */
    public function isActive(string $code): bool
    {
        return $this->activePrograms[$code] ?? false;
    }

    /**
     * Toggle program active status
     * @param string $code Program code
     * @param bool $active Active status
     * @return bool Success
     */
    public function toggleProgram(string $code, bool $active): bool
    {
        if (!isset($this->adapters[$code])) {
            return false;
        }

        $this->activePrograms[$code] = $active;
        
        // In production, this would update the database
        // UPDATE programs SET is_active = ? WHERE code = ?
        
        return true;
    }

    /**
     * Get all available program codes
     * @return array
     */
    public function getAvailablePrograms(): array
    {
        return array_keys($this->adapters);
    }

    /**
     * Get program display names
     * @return array<string, string>
     */
    public function getProgramNames(): array
    {
        $names = [];
        foreach ($this->adapters as $code => $adapter) {
            $names[$code] = $adapter->name();
        }
        return $names;
    }
}