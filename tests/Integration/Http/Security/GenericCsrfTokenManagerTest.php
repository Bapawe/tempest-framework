<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Security;

use Override;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Clock\Clock;
use Tempest\Core\FrameworkKernel;
use Tempest\Http\Security\GenericCsrfTokenManager;
use Tempest\Http\Session\Managers\FileSessionManager;
use Tempest\Http\Session\Session;
use Tempest\Http\Session\SessionConfig;
use Tempest\Http\Session\SessionManager;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;

use function Tempest\Support\Filesystem\delete_directory;
use function Tempest\Support\Filesystem\ensure_directory_empty;

final class GenericCsrfTokenManagerTest extends FrameworkIntegrationTestCase
{
    private string $path = __DIR__ . '/../Fixtures/tmp';

    private GenericCsrfTokenManager $genericCsrfTokenManager;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ensure_directory_empty($this->path);

        $this->container->get(FrameworkKernel::class)->internalStorage = realpath($this->path);

        $this->container->config(new SessionConfig(path: 'sessions'));
        $this->container->singleton(
            SessionManager::class,
            fn () => new FileSessionManager(
                $this->container->get(Clock::class),
                $this->container->get(SessionConfig::class),
            ),
        );

        $this->genericCsrfTokenManager = new GenericCsrfTokenManager($this->container->get(Session::class));
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        delete_directory($this->path);
    }

    #[Test]
    public function generate_token(): void
    {
        $tokenA = $this->genericCsrfTokenManager->generateToken();
        $tokenB = $this->genericCsrfTokenManager->generateToken();

        $this->assertNotSame($tokenA, $tokenB);
    }

    #[Test]
    public function get_token(): void
    {
        $tokenA = $this->genericCsrfTokenManager->getToken();
        $tokenB = $this->genericCsrfTokenManager->getToken();

        $this->assertSame($tokenA, $tokenB);
    }

    #[Test]
    public function it_can_refresh_token(): void
    {
        $token = $this->genericCsrfTokenManager->getToken();

        $newToken = $this->genericCsrfTokenManager->refreshToken();

        $this->assertNotSame($token, $newToken);
    }

    #[Test]
    public function is_token_valid(): void
    {
        $token = $this->genericCsrfTokenManager->getToken();

        $isValid = $this->genericCsrfTokenManager->isTokenValid($token);

        $this->assertTrue($isValid);
    }
}
