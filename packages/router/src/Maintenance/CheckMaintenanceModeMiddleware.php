<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Support\Priority;

/**
 * Answers every request with a `503 Service Unavailable` while the application is down.
 *
 * It runs after `HandleRouteExceptionMiddleware`, so its exception is rendered, and before `MatchRouteMiddleware`,
 * so unknown URLs get a 503 instead of a 404. The state is read on every request, so that running workers
 * pick up `maintenance:enable` and `maintenance:disable` without a restart.
 */
#[Priority(Priority::FRAMEWORK - 30)]
final readonly class CheckMaintenanceModeMiddleware implements HttpMiddleware
{
    public function __construct(
        private MaintenanceModeStore $store,
        private MaintenanceConfig $config,
    ) {}

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if ($this->isExcluded($request->path)) {
            return $next($request);
        }

        $state = $this->store->current();

        if ($state instanceof MaintenanceModeState) {
            throw new MaintenanceModeWasActive(retry: $state->retry);
        }

        return $next($request);
    }

    /**
     * An exact pattern matches only the identical path. A pattern ending in `*` matches any path that
     * starts with what comes before the `*`, followed by at least one more character.
     */
    private function isExcluded(string $path): bool
    {
        foreach ($this->config->excludedPaths as $pattern) {
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
