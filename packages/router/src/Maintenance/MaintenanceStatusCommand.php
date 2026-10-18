<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Console\Console;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;

final readonly class MaintenanceStatusCommand
{
    public function __construct(
        private Console $console,
        private MaintenanceMode $maintenanceMode,
    ) {}

    #[ConsoleCommand(
        name: 'maintenance:status',
        description: 'Tells whether the application is in maintenance mode',
    )]
    public function __invoke(): ExitCode
    {
        $state = $this->maintenanceMode->current();

        if ($state instanceof MaintenanceModeState) {
            $since = $state->enabledSince->format('yyyy-MM-dd HH:mm:ss');
            $timezone = $state->enabledSince->getTimezone()->value;

            $this->console->info("Maintenance mode has been enabled since {$since} ({$timezone}).");
            $this->console->writeln(match ($state->retry) {
                null => 'No retry value is set, so 503 responses carry no `Retry-After` header.',
                default => "Clients are told to retry after {$state->retry} seconds.",
            });

            return ExitCode::ERROR;
        }

        $this->console->success('Maintenance mode is disabled.');

        return ExitCode::SUCCESS;
    }
}
