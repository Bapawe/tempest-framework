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
        private MaintenanceConfig $config,
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

            $excludedPaths = $state->excludedPaths === [] ? '<style="dim">none</style>' : implode(', ', $state->excludedPaths);

            $this->console->keyValue('Excluded paths', $excludedPaths);

            if (! $this->hasSamePaths($state->excludedPaths, $this->config->excludedPaths)) {
                $this->console->warning('The configured excluded paths differ from the saved ones. Run `maintenance:enable` again to apply them.');
            }

            return ExitCode::ERROR;
        }

        $this->console->keyValue('Status', '<style="fg-green bold">DISABLED</style>');

        return ExitCode::SUCCESS;
    }

    /**
     * Compares the paths as sets of exact strings, ignoring order and duplicates, the same way they are matched.
     *
     * @param string[] $saved
     * @param string[] $configured
     */
    private function hasSamePaths(array $saved, array $configured): bool
    {
        return array_diff($saved, $configured) === [] && array_diff($configured, $saved) === [];
    }
}
