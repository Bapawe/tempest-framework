<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

/**
 * The request paths that stay reachable while maintenance mode is enabled. A pattern is either an exact path, or a
 * prefix ending in `*` that matches any path starting with that prefix, followed by at least one more character.
 */
final readonly class ExcludedPaths
{
    /** @var string[] */
    public array $patterns;

    /**
     * @param array<mixed> $patterns Entries that aren't strings are skipped, since they can come from a hand-edited
     *                               config or `state.json`.
     */
    public function __construct(array $patterns = [])
    {
        $this->patterns = array_values(array_filter($patterns, is_string(...)));
    }

    /**
     * Whether the path stays reachable. A regex that can't be used, such as one too large for PCRE, excludes nothing,
     * so maintenance mode stays enabled. The early check in `maintenance.stub.php` matches the same way.
     */
    public function matches(string $path): bool
    {
        $regex = $this->compile();

        return $regex !== null && @preg_match($regex, $path) === 1;
    }

    /**
     * Compiles the patterns into a single regex, or `null` when there are no patterns. `maintenance:enable` saves it
     * for the early check, which can't load this class. Paths are matched byte by byte, like the patterns are written.
     * The patterns are sorted and deduplicated first, so that the same set of patterns always compiles to the same
     * regex.
     */
    public function compile(): ?string
    {
        if ($this->patterns === []) {
            return null;
        }

        $patterns = array_unique($this->patterns);
        sort($patterns, SORT_STRING);

        $alternatives = array_map(
            static fn (string $pattern): string => str_ends_with($pattern, '*')
                ? preg_quote(substr($pattern, 0, -1), '#') . '.+'
                : preg_quote($pattern, '#'),
            $patterns,
        );

        return '#^(?:' . implode('|', $alternatives) . ')\z#s';
    }

    /**
     * Whether both hold the same set of patterns, ignoring order and duplicates. Nothing else is normalised.
     */
    public function equals(self $other): bool
    {
        return $this->compile() === $other->compile();
    }

    /**
     * Lists the patterns as `maintenance:status` shows them, in the order they are configured, or `null` when there
     * are no patterns. It is only meant to be read, never parsed back into patterns.
     */
    public function describe(): ?string
    {
        if ($this->patterns === []) {
            return null;
        }

        return implode(', ', $this->patterns);
    }
}
