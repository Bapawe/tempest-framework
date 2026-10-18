<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Clock\Clock;
use Tempest\Console\Console;
use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\Middleware\CautionMiddleware;
use Tempest\Console\Middleware\ForceMiddleware;

final readonly class MaintenanceEnableCommand
{
    public function __construct(
        private Console $console,
        private MaintenanceModeStore $store,
        private Clock $clock,
    ) {}

    #[ConsoleCommand(
        name: 'maintenance:enable',
        description: 'Enables maintenance mode, taking the application down so that HTTP requests get a 503',
        middleware: [ForceMiddleware::class, CautionMiddleware::class],
    )]
    public function __invoke(
        #[ConsoleArgument(description: 'Seconds after which clients may try again, sent as the `Retry-After` header')]
        ?int $retry = null,
    ): ExitCode {
        if ($retry !== null && $retry < 1) {
            $this->console->error('The `--retry` value must be at least 1 second.');

            return ExitCode::INVALID;
        }

        $current = $this->store->current();

        if ($current instanceof MaintenanceModeState) {
            $this->store->enable(new MaintenanceModeState(downSince: $current->downSince, retry: $retry));
            $this->console->info('The application was already down.');

            return ExitCode::SUCCESS;
        }

        $this->store->enable(new MaintenanceModeState(downSince: $this->clock->now(), retry: $retry));
        $this->console->success('The application is now down.');

        return ExitCode::SUCCESS;
    }
}
