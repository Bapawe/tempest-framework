<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tempest\Core\Environment;
use Tempest\Http\Status;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;
use Tests\Tempest\Integration\Http\Maintenance\Fixtures\MaintenanceModeController;

/**
 * @internal
 */
final class MaintenanceModeConfirmationTest extends FrameworkIntegrationTestCase
{
    use UsesTemporaryInternalStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http->registerRoute([MaintenanceModeController::class, 'page']);
    }

    #[Test]
    #[TestWith([Environment::PRODUCTION])]
    #[TestWith([Environment::STAGING])]
    public function enabling_asks_for_confirmation_in_caution_environments(Environment $environment): void
    {
        $this->container->singleton(Environment::class, $environment);

        $this->console
            ->call('maintenance:enable')
            ->assertContains('Do you wish to continue?')
            ->submit('yes')
            ->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    #[TestWith([Environment::PRODUCTION])]
    #[TestWith([Environment::STAGING])]
    public function declining_the_confirmation_leaves_maintenance_mode_disabled(Environment $environment): void
    {
        $this->container->singleton(Environment::class, $environment);

        $this->console
            ->call('maintenance:enable')
            ->deny()
            ->assertCancelled();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk()
            ->assertSee('Handled normally');
    }

    #[Test]
    #[TestWith([Environment::PRODUCTION])]
    #[TestWith([Environment::STAGING])]
    public function enabling_with_force_does_not_ask(Environment $environment): void
    {
        $this->container->singleton(Environment::class, $environment);

        $this->console
            ->call('maintenance:enable --force')
            ->assertDoesNotContain('Do you wish to continue?')
            ->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function enabling_locally_does_not_ask(): void
    {
        $this->container->singleton(Environment::class, Environment::LOCAL);

        $this->console
            ->call('maintenance:enable')
            ->assertDoesNotContain('Do you wish to continue?')
            ->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    #[TestWith([Environment::PRODUCTION])]
    #[TestWith([Environment::STAGING])]
    public function disabling_never_asks(Environment $environment): void
    {
        $this->container->singleton(Environment::class, $environment);

        $this->console->call('maintenance:enable --force')->assertSuccess();

        $this->console
            ->call('maintenance:disable')
            ->assertDoesNotContain('Do you wish to continue?')
            ->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk()
            ->assertSee('Handled normally');
    }
}
