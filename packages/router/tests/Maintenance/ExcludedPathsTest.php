<?php

declare(strict_types=1);

namespace Tempest\Router\Tests\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tempest\Router\Maintenance\ExcludedPaths;

/**
 * @internal
 */
final class ExcludedPathsTest extends TestCase
{
    #[Test]
    #[TestWith(['/health', '/health'])]
    #[TestWith(['/health/*', '/health/live'])]
    #[TestWith(['/health/*', '/health/ready/database'])]
    #[TestWith(['/health/*', '/health//'])]
    #[TestWith(['/health*', '/healthz'])]
    #[TestWith(['*', '/'])]
    #[TestWith(['*', '/anything/at/all'])]
    #[TestWith(['/api/v1.0', '/api/v1.0'])]
    #[TestWith(['/api/v1.0/*', '/api/v1.0/users'])]
    #[TestWith(['/a+b', '/a+b'])]
    #[TestWith(['/(group)', '/(group)'])]
    #[TestWith(['/a#b', '/a#b'])]
    #[TestWith(['/[x]|y', '/[x]|y'])]
    #[TestWith(['/caf%C3%A9', '/caf%C3%A9'])]
    #[TestWith(['/café', '/café'])]
    #[TestWith(['/a*b', '/a*b'])]
    #[TestWith(['/a**', '/a*b'])]
    #[TestWith(['/health/*', "/health/\n"])]
    public function a_matching_path_is_excluded(string $pattern, string $path): void
    {
        $this->assertTrue(new ExcludedPaths([$pattern])->matches($path));
    }

    #[Test]
    #[TestWith(['/health', '/health/'])]
    #[TestWith(['/health', '/healthz'])]
    #[TestWith(['/health', '/xhealth'])]
    #[TestWith(['/health', '/admin/health'])]
    #[TestWith(['/health', '/Health'])]
    #[TestWith(['/health', "/health\n"])]
    #[TestWith(['/health', ''])]
    #[TestWith(['/health/*', '/health'])]
    #[TestWith(['/health/*', '/health/'])]
    #[TestWith(['/health/*', '/healthz/live'])]
    #[TestWith(['/health/*', '/admin/health/live'])]
    #[TestWith(['*', ''])]
    #[TestWith(['/api/v1.0', '/api/v1x0'])]
    #[TestWith(['/api/v1.0/*', '/api/v1x0/users'])]
    #[TestWith(['/a+b', '/aab'])]
    #[TestWith(['/(group)', '/group'])]
    #[TestWith(['/[x]|y', '/x'])]
    #[TestWith(['/[x]|y', 'y'])]
    #[TestWith(['/caf%C3%A9', '/café'])]
    #[TestWith(['/a*b', '/axb'])]
    #[TestWith(['/a**', '/a*'])]
    #[TestWith(['/a**', '/ab'])]
    public function a_path_that_does_not_match_is_not_excluded(string $pattern, string $path): void
    {
        $this->assertFalse(new ExcludedPaths([$pattern])->matches($path));
    }

    #[Test]
    public function any_of_several_patterns_excludes_a_path(): void
    {
        $excludedPaths = new ExcludedPaths(['/health', '/status/*']);

        $this->assertTrue($excludedPaths->matches('/health'));
        $this->assertTrue($excludedPaths->matches('/status/live'));
        $this->assertFalse($excludedPaths->matches('/status'));
        $this->assertFalse($excludedPaths->matches('/anything'));
    }

    #[Test]
    public function an_empty_pattern_only_matches_an_empty_path(): void
    {
        $excludedPaths = new ExcludedPaths(['']);

        $this->assertTrue($excludedPaths->matches(''));
        $this->assertFalse($excludedPaths->matches('/'));
    }

    #[Test]
    public function invalid_utf8_paths_are_matched_byte_by_byte(): void
    {
        $excludedPaths = new ExcludedPaths(["/\xFF/*"]);

        $this->assertTrue($excludedPaths->matches("/\xFF/a"));
        $this->assertFalse($excludedPaths->matches("/\xFE/a"));
    }

    #[Test]
    public function no_patterns_compile_to_no_regex(): void
    {
        $this->assertNull(new ExcludedPaths()->compile());
    }

    #[Test]
    public function patterns_compile_to_an_anchored_regex(): void
    {
        $this->assertSame('#^(?:/health|/health/.+)\z#s', new ExcludedPaths(['/health/*', '/health'])->compile());
    }

    #[Test]
    public function no_patterns_match_nothing(): void
    {
        $excludedPaths = new ExcludedPaths();

        $this->assertFalse($excludedPaths->matches('/health'));
        $this->assertFalse($excludedPaths->matches(''));
    }

    #[Test]
    public function patterns_that_are_not_strings_are_skipped(): void
    {
        $excludedPaths = new ExcludedPaths([42, null, ['/status'], '/health']);

        $this->assertSame(['/health'], $excludedPaths->patterns);
        $this->assertTrue($excludedPaths->matches('/health'));
        $this->assertFalse($excludedPaths->matches('42'));
    }

    #[Test]
    public function patterns_that_compile_to_a_regex_too_large_for_pcre_match_nothing(): void
    {
        $excludedPaths = $this->tooManyPatterns();

        $this->assertFalse($excludedPaths->matches('/path-1'));
    }

    #[Test]
    public function the_warning_of_a_regex_too_large_for_pcre_is_silenced(): void
    {
        $excludedPaths = $this->tooManyPatterns();

        // Like the framework's error handler, which ignores errors silenced with `@`
        set_error_handler(function (int $code, string $message): bool {
            if ((error_reporting() & $code) === 0) {
                return false;
            }

            $this->fail("Unexpected warning: {$message}");
        });

        try {
            $this->assertFalse($excludedPaths->matches('/path-1'));
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    #[TestWith([['/health'], ['/health']])]
    #[TestWith([
        ['/health',   '/status/*'],
        ['/status/*', '/health'],
    ])]
    #[TestWith([['/health', '/health'], ['/health']])]
    #[TestWith([['/b', '/a', '/b', '/c'], ['/c', '/a', '/b']])]
    public function the_same_set_of_patterns_is_equal(array $patterns, array $samePatterns): void
    {
        $this->assertSame(new ExcludedPaths($patterns)->compile(), new ExcludedPaths($samePatterns)->compile());
        $this->assertTrue(new ExcludedPaths($patterns)->equals(new ExcludedPaths($samePatterns)));
    }

    #[Test]
    #[TestWith([[], ['/health']])]
    #[TestWith([['/health'], ['/health/']])]
    #[TestWith([['/health'], ['/Health']])]
    #[TestWith([['/health'], ['/health', '/status']])]
    #[TestWith([['/health/*'], ['/health']])]
    #[TestWith([['/health/*'], ['/health/.+']])]
    public function different_sets_of_patterns_are_not_equal(array $patterns, array $otherPatterns): void
    {
        $this->assertNotSame(new ExcludedPaths($patterns)->compile(), new ExcludedPaths($otherPatterns)->compile());
        $this->assertFalse(new ExcludedPaths($patterns)->equals(new ExcludedPaths($otherPatterns)));
    }

    #[Test]
    public function sorting_the_patterns_does_not_change_what_they_match(): void
    {
        $excludedPaths = new ExcludedPaths(['/health/*', '/health', '/h*']);

        $this->assertTrue($excludedPaths->matches('/health'));
        $this->assertTrue($excludedPaths->matches('/health/live'));
        $this->assertTrue($excludedPaths->matches('/home'));
        $this->assertFalse($excludedPaths->matches('/h'));
    }

    #[Test]
    public function the_patterns_are_described_in_the_configured_order(): void
    {
        $this->assertSame('/status/*, /health', new ExcludedPaths(['/status/*', '/health'])->describe());
    }

    #[Test]
    public function the_description_keeps_duplicates(): void
    {
        $this->assertSame('/health, /health', new ExcludedPaths(['/health', '/health'])->describe());
    }

    #[Test]
    public function the_description_ignores_the_keys(): void
    {
        $this->assertSame('/b, /a', new ExcludedPaths([3 => '/b', 1 => '/a'])->describe());
    }

    #[Test]
    public function no_patterns_have_no_description(): void
    {
        $this->assertNull(new ExcludedPaths()->describe());
    }

    /**
     * Enough patterns that the compiled regex exceeds what PCRE can compile.
     */
    private function tooManyPatterns(): ExcludedPaths
    {
        return new ExcludedPaths(array_map(static fn (int $number): string => "/path-{$number}", range(1, 10_000)));
    }
}
