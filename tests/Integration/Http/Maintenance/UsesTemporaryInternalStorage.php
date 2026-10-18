<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

use function Tempest\Support\Filesystem\create_temporary_directory;
use function Tempest\Support\Filesystem\delete_directory;

/**
 * Points the kernel's internal storage at a temporary directory, so tests don't share maintenance mode state.
 *
 * The priorities run these hooks after `setUp()` has booted the kernel and before `tearDown()` discards it.
 */
trait UsesTemporaryInternalStorage
{
    #[Before(priority: -1)]
    protected function useTemporaryInternalStorage(): void
    {
        $this->kernel->internalStorage = create_temporary_directory('tempest-maintenance-');
    }

    #[After(priority: 1)]
    protected function deleteTemporaryInternalStorage(): void
    {
        delete_directory($this->kernel->internalStorage);
    }
}
