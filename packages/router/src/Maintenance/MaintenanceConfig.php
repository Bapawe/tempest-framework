<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

final class MaintenanceConfig
{
    /**
     * @param string[] $excludedPaths Request paths that stay reachable while maintenance mode is enabled, either exact or ending in `*`.
     */
    public function __construct(
        public array $excludedPaths = [],
    ) {}
}
