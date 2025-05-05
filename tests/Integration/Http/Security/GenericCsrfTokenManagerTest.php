<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Security;

use Override;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Clock\Clock;
use Tempest\Core\FrameworkKernel;
use Tempest\Http\Security\CsrfConfig;
use Tempest\Http\Security\CsrfTokenManager;
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

    private CsrfTokenManager $csrfTokenManager;

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

        $this->csrfTokenManager = new CsrfTokenManager(
            $this->container->get(Session::class),
            $this->container->get(CsrfConfig::class),
        );
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
        $tokenA = $this->csrfTokenManager->generateToken();
        $tokenB = $this->csrfTokenManager->generateToken();

        $this->assertNotSame($tokenA, $tokenB);
    }

    #[Test]
    public function get_token(): void
    {
        $tokenA = $this->csrfTokenManager->getToken();
        $tokenB = $this->csrfTokenManager->getToken();

        $this->assertSame($tokenA, $tokenB);
    }

    #[Test]
    public function it_can_refresh_token(): void
    {
        $token = $this->csrfTokenManager->getToken();

        $newToken = $this->csrfTokenManager->refreshToken();

        $this->assertNotSame($token, $newToken);
    }

    #[Test]
    public function is_token_valid(): void
    {
        $token = $this->csrfTokenManager->getToken();

        $isValid = $this->csrfTokenManager->isTokenValid($token);

        $this->assertTrue($isValid);
    }
}
