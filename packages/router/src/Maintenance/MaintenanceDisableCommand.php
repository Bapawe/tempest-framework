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
        private MaintenanceModeStore $store,
    ) {}

    #[ConsoleCommand(
        name: 'maintenance:disable',
        description: 'Disables maintenance mode, bringing the application back up',
    )]
    public function __invoke(): ExitCode
    {
        $wasDown = $this->store->current() instanceof MaintenanceModeState;

        // Also clears whatever a broken state left behind
        $this->store->disable();

        if (! $wasDown) {
            $this->console->info('The application is already up.');

            return ExitCode::SUCCESS;
        }

        $this->console->success('The application is now up.');

        return ExitCode::SUCCESS;
    }
}
