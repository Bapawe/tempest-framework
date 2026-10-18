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

        $this->console->header('Maintenance mode');

        if ($state instanceof MaintenanceModeState) {
            $since = $state->enabledSince->format('yyyy-MM-dd HH:mm:ss');
            $timezone = $state->enabledSince->getTimezone()->value;

            $this->console->keyValue('Status', '<style="fg-yellow bold">ENABLED</style>');
            $this->console->keyValue('Enabled since', "{$since} ({$timezone})");

            if ($state->retry !== null) {
                $this->console->keyValue('Retry after', "{$state->retry} seconds");
            }

            return ExitCode::ERROR;
        }

        $this->console->keyValue('Status', '<style="fg-green bold">DISABLED</style>');

        return ExitCode::SUCCESS;
    }
}
