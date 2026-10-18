<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Console\Console;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;

final readonly class MaintenanceDisableCommand
{
    public function __construct(
        private Console $console,
        private MaintenanceMode $maintenanceMode,
    ) {}

    #[ConsoleCommand(
        name: 'maintenance:disable',
        description: 'Disables maintenance mode, so that HTTP requests are handled again',
    )]
    public function __invoke(): ExitCode
    {
        $wasEnabled = $this->maintenanceMode->current() instanceof MaintenanceModeState;

        // Also clears whatever a broken state left behind
        $this->maintenanceMode->disable();

        if (! $wasEnabled) {
            $this->console->info('Maintenance mode was not enabled.');

            return ExitCode::SUCCESS;
        }

        $this->console->success('Maintenance mode is now disabled.');

        return ExitCode::SUCCESS;
    }
}
