<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance\Fixtures;

use Tempest\Http\ContentType;
use Tempest\Http\GenericResponse;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Status;
use Tempest\Router\Exceptions\ExceptionRenderer;
use Tempest\Router\Maintenance\MaintenanceModeWasEnabled;
use Throwable;

final readonly class MaintenancePageRenderer implements ExceptionRenderer
{
    public function canRender(Throwable $throwable, Request $request): bool
    {
        return $throwable instanceof MaintenanceModeWasEnabled && $request->accepts(ContentType::HTML);
    }

    public function render(Throwable $throwable): Response
    {
        return new GenericResponse(
            status: Status::SERVICE_UNAVAILABLE,
            body: '<html><body><h1>We will be right back</h1></body></html>',
            headers: ['Content-Type' => ContentType::HTML->value],
        );
    }
}
