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
use Throwable;

final readonly class MaintenanceEnableCommand
{
    public function __construct(
        private Console $console,
        private MaintenanceMode $maintenanceMode,
        private Clock $clock,
        private MaintenancePagePrerenderer $prerenderer,
        private MaintenanceConfig $config,
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

        $current = $this->maintenanceMode->current();
        $page = $this->renderPage($retry);

        if ($current instanceof MaintenanceModeState) {
            $this->maintenanceMode->enable(new MaintenanceModeState(downSince: $current->downSince, retry: $retry, excludedPaths: $this->config->excludedPaths), $page);
            $this->console->info('The application was already down.');

            return ExitCode::SUCCESS;
        }

        $this->maintenanceMode->enable(new MaintenanceModeState(downSince: $this->clock->now(), retry: $retry, excludedPaths: $this->config->excludedPaths), $page);
        $this->console->success('The application is now down.');

        return ExitCode::SUCCESS;
    }

    /**
     * Renders the page served while the application can't boot, falling back to the built-in page so that a broken
     * error page never keeps the application from going down.
     */
    private function renderPage(?int $retry): MaintenancePage
    {
        try {
            return $this->prerenderer->render(new MaintenanceModeWasActive(retry: $retry));
        } catch (Throwable $throwable) {
            $this->console->warning("The maintenance page could not be rendered, so the built-in page is served while the application can't boot: {$throwable->getMessage()}");

            return MaintenancePage::builtIn();
        }
    }
}
