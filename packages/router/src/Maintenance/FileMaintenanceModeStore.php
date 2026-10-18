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
 * Keeps the maintenance mode state in a file in the framework's internal storage directory. It only affects the server it runs on.
 */
final readonly class FileMaintenanceModeStore implements MaintenanceModeStore
{
    private const string FILENAME = 'maintenance.json';

    private const string STUB_FILENAME = 'maintenance.php';

    private const string HTML_FILENAME = 'maintenance.html';

    private const string JSON_BODY_FILENAME = 'maintenance-body.json';

    public function __construct(
        private Kernel $kernel,
    ) {}

    /**
     * A state that can't be read or parsed counts as up, so a broken file never keeps the application down. The early
     * check in `maintenance.stub.php` validates the state the same way.
     */
    public function current(): ?MaintenanceModeState
    {
        $path = $this->path(self::FILENAME);

        if (! Filesystem\is_file($path)) {
            return null;
        }

        try {
            $data = Json\decode(Filesystem\read_file($path));
        } catch (FilesystemException|JsonException) {
            return null;
        }

        if (! is_array($data) || ! is_int($data['down_since'] ?? null)) {
            return null;
        }

        return new MaintenanceModeState(
            downSince: DateTime::fromTimestamp($data['down_since']),
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
        Filesystem\write_file($this->path(self::HTML_FILENAME), $page->html);
        Filesystem\write_file($this->path(self::JSON_BODY_FILENAME), $page->json);

        $temporary = $this->path(self::FILENAME . '.tmp');

        Filesystem\write_json($temporary, [
            'down_since' => $state->downSince->getTimestamp()->getSeconds(),
            'retry' => $state->retry,
            'excluded_paths' => $state->excludedPaths,
        ]);

        Filesystem\move($temporary, $this->path(self::FILENAME), overwrite: true);
        Filesystem\copy_file(__DIR__ . '/maintenance.stub.php', $this->path(self::STUB_FILENAME), overwrite: true);
    }

    /**
     * The state goes first, so a stub or bodies left behind by a failure here let requests through.
     */
    public function disable(): void
    {
        foreach ([self::FILENAME, self::STUB_FILENAME, self::HTML_FILENAME, self::JSON_BODY_FILENAME] as $filename) {
            if (! Filesystem\is_file($this->path($filename))) {
                continue;
            }

            Filesystem\delete_file($this->path($filename));
        }
    }

    /**
     * The internal storage directory is resolved on every call, since it may change after the store was created.
     */
    private function path(string $filename): string
    {
        return $this->kernel->internalStorage . '/' . $filename;
    }
}
