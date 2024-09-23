<?php

declare(strict_types=1);

namespace Integration\Console\Middleware;

use Tests\Tempest\Integration\FrameworkIntegrationTestCase;

/**
 * @internal
 */
final class ResolveOrRescueMiddlewareTest extends FrameworkIntegrationTestCase
{
    public function test_has_single_similar_commands(): void
    {
        $this->console
            ->call('discovery:sta')
            ->assertSee('Did you mean discovery:status?');
    }

    public function test_has_multiple_similar_commands(): void
    {
        $this->console
            ->call('discovery')
            ->assertSee('Did you mean to run one of these?  [discovery:cache/discovery:status/discovery:clear]');
    }

    public function test_levenshtein(): void
    {
        $this->console
            ->call('bascovery:status')
            ->assertSee('Did you mean discovery:status?');
    }
}
