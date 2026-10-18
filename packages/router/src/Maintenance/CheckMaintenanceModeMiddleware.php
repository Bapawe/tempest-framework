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
 * It runs after `HandleRouteExceptionMiddleware`, so its exception is rendered, and before `MatchRouteMiddleware`,
 * so unknown URLs get a 503 instead of a 404. The state is read on every request, so that running workers
 * pick up `maintenance:enable` and `maintenance:disable` without a restart. Excluded paths come from the state, as saved
 * by `maintenance:enable`, so this check and the early check in `maintenance.stub.php` always agree.
 */
#[Priority(Priority::FRAMEWORK - 30)]
final readonly class CheckMaintenanceModeMiddleware implements HttpMiddleware
{
    public function __construct(
        private MaintenanceMode $maintenanceMode,
    ) {}

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $state = $this->maintenanceMode->current();

        if ($state instanceof MaintenanceModeState && ! $this->isExcluded($request->path, $state->excludedPaths)) {
            throw new MaintenanceModeWasActive(retry: $state->retry);
        }

        return $next($request);
    }

    /**
     * An exact pattern matches only the identical path. A pattern ending in `*` matches any path that
     * starts with what comes before the `*`, followed by at least one more character. The early check in
     * `maintenance.stub.php` applies the same rules.
     *
     * @param string[] $excludedPaths
     */
    private function isExcluded(string $path, array $excludedPaths): bool
    {
        foreach ($excludedPaths as $pattern) {
            if (! str_ends_with($pattern, '*')) {
                if ($path === $pattern) {
                    return true;
                }

                continue;
            }

            $prefix = substr($pattern, 0, -1);

            if (strlen($path) > strlen($prefix) && str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
