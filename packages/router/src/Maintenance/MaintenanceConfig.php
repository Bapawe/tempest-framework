<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

final class MaintenanceConfig
{
    /**
     * @param string[] $excludedPaths Request paths that stay reachable while the application is down. A pattern is either an exact path, such as `/health`, or ends in `*`. A pattern ending in `*` matches any path that starts with the text before the `*`, followed by at least one more character: `/webhooks/*` matches `/webhooks/stripe`, but not `/webhooks/` or `/webhooks`. The prefix is compared as plain text, so `/api*` also matches `/api2`.
     */
    public function __construct(
        public array $excludedPaths = [],
    ) {}
}
