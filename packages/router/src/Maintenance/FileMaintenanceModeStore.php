<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Core\Kernel;
use Tempest\DateTime\DateTime;
use Tempest\Support\Filesystem;

/**
 * Keeps the maintenance mode state in a file in the framework's internal storage directory. It only affects the server it runs on.
 */
final readonly class FileMaintenanceModeStore implements MaintenanceModeStore
{
    private const string FILENAME = 'maintenance.json';

    public function __construct(
        private Kernel $kernel,
    ) {}

    public function current(): ?MaintenanceModeState
    {
        $path = $this->path();

        if (! Filesystem\is_file($path)) {
            return null;
        }

        $data = Filesystem\read_json($path);

        return new MaintenanceModeState(
            downSince: DateTime::fromTimestamp($data['down_since']),
            retry: $data['retry'] ?? null,
        );
    }

    public function enable(MaintenanceModeState $state): void
    {
        Filesystem\write_json($this->path(), [
            'down_since' => $state->downSince->getTimestamp()->getSeconds(),
            'retry' => $state->retry,
        ]);
    }

    public function disable(): void
    {
        $path = $this->path();

        if (Filesystem\is_file($path)) {
            Filesystem\delete_file($path);
        }
    }

    /**
     * The internal storage directory is resolved on every call, since it may change after the store was created.
     */
    private function path(): string
    {
        return $this->kernel->internalStorage . '/' . self::FILENAME;
    }
}
