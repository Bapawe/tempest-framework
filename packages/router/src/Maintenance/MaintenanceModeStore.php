<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

/**
 * Reads and writes the maintenance mode state.
 */
interface MaintenanceModeStore
{
    /**
     * Returns the current state, or `null` when the application is up.
     */
    public function current(): ?MaintenanceModeState;

    /**
     * Takes the application down with the given state, replacing any existing one. Stores that answer requests
     * before the application boots serve the given page; others may ignore it.
     */
    public function enable(MaintenanceModeState $state, MaintenancePage $page): void;

    /**
     * Brings the application back up.
     */
    public function disable(): void;
}
