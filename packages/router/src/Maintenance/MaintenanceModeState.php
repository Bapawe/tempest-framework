<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\DateTime\DateTimeInterface;

/**
 * Describes maintenance mode while it is enabled. When it is disabled, there is no state.
 */
final readonly class MaintenanceModeState
{
    public function __construct(
        public DateTimeInterface $enabledSince,

        /** How many seconds clients are told to wait before retrying, if a value was given. */
        public ?int $retry = null,

        /**
         * The excluded paths configured when maintenance mode was enabled. Both maintenance checks match against
         * these, so changes to the config take effect the next time `maintenance:enable` runs.
         *
         * @var string[]
         */
        public array $excludedPaths = [],
    ) {}
}
