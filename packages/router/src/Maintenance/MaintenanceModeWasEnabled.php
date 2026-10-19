<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Exception;
use Tempest\Router\Exceptions\RouterException;

/**
 * Thrown when a request reaches the application while maintenance mode is enabled. Renders as a `503 Service Unavailable`,
 * with a `Retry-After` header when a retry value was given.
 */
final class MaintenanceModeWasEnabled extends Exception implements RouterException
{
    /**
     * @param int|null $retry Seconds after which clients may try again, sent as `Retry-After`.
     */
    public function __construct(
        private(set) readonly ?int $retry = null,
    ) {
        parent::__construct('Maintenance mode is enabled.');
    }
}
