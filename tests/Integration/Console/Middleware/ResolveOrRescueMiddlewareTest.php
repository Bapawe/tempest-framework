<?php

declare(strict_types=1);

namespace Integration\Console\Middleware;

use Tests\Tempest\Integration\FrameworkIntegrationTestCase;

/**
 * @internal
 */
final class ResolveOrRescueMiddlewareTest extends FrameworkIntegrationTestCase
{
    public function test_rescue(): void
    {
        $this->console
            ->call('rescue-test:')
            ->assertSee('Did you mean rescue-test:rescue?');
    }

    public function test_rescue_levenshtein(): void
    {
        $this->console
            ->call('rescue-test:reescue')
            ->assertSee('Did you mean rescue-test:rescue?');
    }
}
