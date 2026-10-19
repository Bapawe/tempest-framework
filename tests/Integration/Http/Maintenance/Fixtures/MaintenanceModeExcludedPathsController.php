<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance\Fixtures;

use Tempest\Router\Get;

final readonly class MaintenanceModeExcludedPathsController
{
    #[Get('/health')]
    public function health(): string
    {
        return 'Healthy';
    }

    #[Get('/health/live')]
    public function live(): string
    {
        return 'Alive';
    }

    #[Get('/health/ready/database')]
    public function databaseReady(): string
    {
        return 'Database ready';
    }
}
