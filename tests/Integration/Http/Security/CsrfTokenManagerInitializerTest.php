<?php

declare(strict_types=1);

namespace Integration\Http\Security;

use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Security\CsrfTokenManager;
use Tempest\Http\Security\CsrfTokenManagerInitializer;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;

final class CsrfTokenManagerInitializerTest extends FrameworkIntegrationTestCase
{
    #[Test]
    public function it_can_initialize_csrf_token_manager(): void
    {
        $initializer = new CsrfTokenManagerInitializer();

        $this->assertInstanceOf(
            CsrfTokenManager::class,
            $initializer->initialize($this->container),
        );
    }
}
