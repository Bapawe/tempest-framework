<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Support\Priority;

/**
 * Answers every request with a `503 Service Unavailable` while maintenance mode is enabled.
 *
 * It runs before `MatchRouteMiddleware`, so unknown URLs get a 503 instead of a 404. The state is read on every
 * request, so that running workers pick up `maintenance:enable` and `maintenance:disable` without a restart. Excluded
 * paths come from the state, as saved by `maintenance:enable`, so this check and the early check in
 * `maintenance.stub.php` agree.
 */
#[Priority(Priority::FRAMEWORK - 31)]
final readonly class CheckMaintenanceModeMiddleware implements HttpMiddleware
{
    public function __construct(
        private MaintenanceMode $maintenanceMode,
    ) {}

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $state = $this->maintenanceMode->current();

        if ($state instanceof MaintenanceModeState && ! $state->excludedPaths->matches($request->path)) {
            throw new MaintenanceModeWasEnabled(retry: $state->retry);
        }

        return $next($request);
    }
}
