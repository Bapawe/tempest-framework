<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance\Fixtures;

use RuntimeException;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Router\Exceptions\ExceptionRenderer;
use Tempest\Router\Maintenance\MaintenanceModeWasActive;
use Throwable;

final readonly class BrokenMaintenancePageRenderer implements ExceptionRenderer
{
    public function canRender(Throwable $throwable, Request $request): bool
    {
        return $throwable instanceof MaintenanceModeWasActive;
    }

    public function render(Throwable $throwable): Response
    {
        throw new RuntimeException('The maintenance page view is broken');
    }
}
