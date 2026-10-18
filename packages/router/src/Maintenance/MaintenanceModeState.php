<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\DateTime\DateTimeInterface;

/**
 * Describes an application that is down. When the application is up, there is no state.
 */
final readonly class MaintenanceModeState
{
    public function __construct(
        /** When the application went down. */
        public DateTimeInterface $downSince,

        /** How many seconds clients are told to wait before retrying, if a value was given. */
        public ?int $retry = null,

        /**
         * The excluded paths configured when the application went down. Both maintenance checks match against
         * these, so changes to the config take effect the next time `maintenance:enable` runs.
         *
         * @var string[]
         */
        public array $excludedPaths = [],
    ) {}
}
