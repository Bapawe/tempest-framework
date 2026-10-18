<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Core\Kernel;
use Tempest\DateTime\DateTime;
use Tempest\Support\Filesystem;
use Tempest\Support\Filesystem\Exceptions\FilesystemException;
use Tempest\Support\Json;
use Tempest\Support\Json\Exception\JsonException;

/**
 * Keeps the maintenance mode state in a `maintenance` folder in the framework's internal storage directory. It only
 * affects the server it runs on.
 */
final readonly class MaintenanceMode
{
    private const string DIRECTORY = 'maintenance';

    private const string STATE_FILENAME = 'state.json';

    private const string CHECK_FILENAME = 'check.php';

    private const string HTML_PAGE_FILENAME = 'page.html';

    private const string JSON_PAGE_FILENAME = 'page.json';

    public function __construct(
        private Kernel $kernel,
    ) {}

    /**
     * Returns the current state, or `null` when maintenance mode is disabled. A state that can't be read or parsed counts
     * as disabled, so a broken file never keeps maintenance mode enabled. The early check in `maintenance.stub.php`
     * validates the state the same way.
     */
    public function current(): ?MaintenanceModeState
    {
        $path = $this->path(self::STATE_FILENAME);

        if (! Filesystem\is_file($path)) {
            return null;
        }

        try {
            $data = Json\decode(Filesystem\read_file($path));
        } catch (FilesystemException|JsonException) {
            return null;
        }

        if (! is_array($data) || ! is_int($data['enabled_since'] ?? null)) {
            return null;
        }

        return new MaintenanceModeState(
            enabledSince: DateTime::fromTimestamp($data['enabled_since']),
            retry: is_int($data['retry'] ?? null) ? $data['retry'] : null,
            excludedPaths: is_array($data['excluded_paths'] ?? null) ? array_values(array_filter($data['excluded_paths'], is_string(...))) : [],
        );
    }

    /**
     * The files are written so that the early check never acts on a half-written state: first the bodies, then the
     * state through a rename, then the stub that reads them.
     */
    public function enable(MaintenanceModeState $state, MaintenancePage $page): void
    {
        Filesystem\create_directory($this->directory());
        Filesystem\write_file($this->path(self::HTML_PAGE_FILENAME), $page->html);
        Filesystem\write_file($this->path(self::JSON_PAGE_FILENAME), $page->json);

        $temporary = $this->path(self::STATE_FILENAME . '.tmp');

        Filesystem\write_json($temporary, [
            'enabled_since' => $state->enabledSince->getTimestamp()->getSeconds(),
            'retry' => $state->retry,
            'excluded_paths' => $state->excludedPaths,
        ]);

        Filesystem\move($temporary, $this->path(self::STATE_FILENAME), overwrite: true);
        Filesystem\copy_file(__DIR__ . '/maintenance.stub.php', $this->path(self::CHECK_FILENAME), overwrite: true);
    }

    /**
     * Removes the folder as a whole, which also clears whatever a broken state left behind. The state goes first, so a
     * stub or bodies left behind by a failure here let requests through.
     */
    public function disable(): void
    {
        if (Filesystem\is_file($this->path(self::STATE_FILENAME))) {
            Filesystem\delete_file($this->path(self::STATE_FILENAME));
        }

        if (Filesystem\is_directory($this->directory())) {
            Filesystem\delete_directory($this->directory());
        }
    }

    /**
     * The internal storage directory is resolved on every call, since it may change after this class was created.
     */
    private function directory(): string
    {
        return $this->kernel->internalStorage . '/' . self::DIRECTORY;
    }

    private function path(string $filename): string
    {
        return $this->directory() . '/' . $filename;
    }
}
