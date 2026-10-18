<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tempest\Core\Environment;
use Tempest\DateTime\Duration;
use Tempest\Http\ContentType;
use Tempest\Http\Status;
use Tempest\Router\RouteConfig;
use Tempest\Support\Priority;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;
use Tests\Tempest\Integration\Http\Maintenance\Fixtures\MaintenanceModeController;
use Tests\Tempest\Integration\Http\Maintenance\Fixtures\MaintenancePageRenderer;

/**
 * @internal
 */
final class MaintenanceModeTest extends FrameworkIntegrationTestCase
{
    use UsesTemporaryInternalStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http->registerRoute([MaintenanceModeController::class, 'page']);
    }

    #[Test]
    public function html_requests_get_a_503_error_page_while_down(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::HTML)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertSee('HTTP')
            ->assertSee('503')
            ->assertNotSee('Handled normally');
    }

    #[Test]
    public function json_requests_get_a_503_json_error_while_down(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::JSON)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertJson(['message' => 'Service Unavailable']);
    }

    #[Test]
    public function html_and_json_503s_carry_a_retry_after_header_when_a_retry_is_given(): void
    {
        $this->console->call('maintenance:enable --retry=600')->assertSuccess();

        $this->http
            ->as(ContentType::HTML)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertHeaderContains('Retry-After', '600');

        $this->http
            ->as(ContentType::JSON)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertHeaderContains('Retry-After', '600');
    }

    #[Test]
    public function html_and_json_503s_carry_no_retry_after_header_without_a_retry(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::HTML)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertDoesNotHaveHeader('Retry-After');

        $this->http
            ->as(ContentType::JSON)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertDoesNotHaveHeader('Retry-After');
    }

    #[Test]
    public function a_custom_exception_renderer_renders_the_maintenance_page(): void
    {
        $this->container->get(RouteConfig::class)->addExceptionRenderer(MaintenancePageRenderer::class, Priority::HIGH);

        $this->console->call('maintenance:enable --retry=600')->assertSuccess();

        $this->http
            ->as(ContentType::HTML)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertSee('We will be right back')
            ->assertHeaderContains('Retry-After', '600');

        $this->http
            ->as(ContentType::JSON)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertJson(['message' => 'Service Unavailable']);
    }

    #[Test]
    public function unknown_urls_get_a_503_instead_of_a_404_while_down(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::HTML)
            ->get('/maintenance-mode/does-not-exist')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function requests_are_handled_normally_again_after_disabling(): void
    {
        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk()
            ->assertSee('Handled normally');

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);

        $this->console
            ->call('maintenance:disable')
            ->assertSuccess()
            ->assertContains('The application is now up');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk()
            ->assertSee('Handled normally');
    }

    #[Test]
    public function enabling_twice_succeeds_and_says_it_was_already_down(): void
    {
        $this->console
            ->call('maintenance:enable')
            ->assertSuccess()
            ->assertContains('The application is now down');

        $this->console
            ->call('maintenance:enable')
            ->assertSuccess()
            ->assertContains('The application was already down');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function disabling_while_already_up_succeeds_with_a_note(): void
    {
        $this->console
            ->call('maintenance:disable')
            ->assertSuccess()
            ->assertContains('The application is already up');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk();
    }

    #[Test]
    public function the_local_environment_renders_the_real_503_page_instead_of_the_debug_page(): void
    {
        $this->container->singleton(Environment::class, Environment::LOCAL);

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::HTML)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertSee('The service is temporarily unavailable')
            ->assertNotSee('tempest-hydration');
    }

    #[Test]
    public function the_local_environment_leaves_debug_info_out_of_the_503_json_error(): void
    {
        $this->container->singleton(Environment::class, Environment::LOCAL);

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::JSON)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertJson(['message' => 'Service Unavailable']);
    }

    #[Test]
    public function status_reports_up_and_succeeds_while_up(): void
    {
        $this->console
            ->call('maintenance:status')
            ->assertSuccess()
            ->assertContains('The application is up');
    }

    #[Test]
    public function status_reports_down_since_when_and_errors_while_down(): void
    {
        $this->clock('2025-03-14 09:26:53');

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('The application has been down since 2025-03-14 09:26:53 (UTC)');
    }

    #[Test]
    public function status_reports_the_retry_value_when_one_is_set(): void
    {
        $this->console->call('maintenance:enable --retry=600')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('Clients are told to retry after 600 seconds');
    }

    #[Test]
    public function status_reports_no_retry_value_when_none_is_set(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('No retry value is set')
            ->assertDoesNotContain('retry after');
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([-5])]
    public function enabling_with_a_retry_below_one_is_rejected_and_leaves_the_application_up(int $retry): void
    {
        $this->console
            ->call("maintenance:enable --retry={$retry}")
            ->assertInvalid()
            ->assertContains('The `--retry` value must be at least 1 second');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk()
            ->assertSee('Handled normally');
    }

    #[Test]
    public function enabling_again_with_a_retry_below_one_is_rejected_and_keeps_the_retry_value(): void
    {
        $this->console->call('maintenance:enable --retry=600')->assertSuccess();

        $this->console
            ->call('maintenance:enable --retry=0')
            ->assertInvalid()
            ->assertContains('The `--retry` value must be at least 1 second');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertHeaderContains('Retry-After', '600');
    }

    #[Test]
    public function enabling_again_with_a_retry_replaces_the_retry_value(): void
    {
        $this->console->call('maintenance:enable --retry=600')->assertSuccess();
        $this->console->call('maintenance:enable --retry=120')->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertHeaderContains('Retry-After', '120');

        $this->console
            ->call('maintenance:status')
            ->assertContains('Clients are told to retry after 120 seconds');
    }

    #[Test]
    public function enabling_again_without_a_retry_clears_the_retry_value(): void
    {
        $this->console->call('maintenance:enable --retry=600')->assertSuccess();
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertDoesNotHaveHeader('Retry-After');

        $this->console
            ->call('maintenance:status')
            ->assertContains('No retry value is set');
    }

    #[Test]
    public function enabling_again_with_a_retry_keeps_the_original_down_since_time(): void
    {
        $clock = $this->clock('2025-03-14 09:26:53');

        $this->console->call('maintenance:enable')->assertSuccess();

        $clock->plus(Duration::hours(2));

        $this->console->call('maintenance:enable --retry=600')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('The application has been down since 2025-03-14 09:26:53 (UTC)')
            ->assertContains('Clients are told to retry after 600 seconds');
    }

    #[Test]
    public function enabling_again_keeps_the_original_down_since_time(): void
    {
        $clock = $this->clock('2025-03-14 09:26:53');

        $this->console->call('maintenance:enable')->assertSuccess();

        $clock->plus(Duration::hours(2));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('The application has been down since 2025-03-14 09:26:53 (UTC)');
    }

    #[Test]
    public function status_reports_up_again_after_disabling(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->console->call('maintenance:disable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertSuccess()
            ->assertContains('The application is up');
    }

    #[Test]
    public function status_never_asks_for_confirmation_in_production(): void
    {
        $this->container->singleton(Environment::class, Environment::PRODUCTION);

        $this->console
            ->call('maintenance:status')
            ->assertSuccess()
            ->assertContains('The application is up');

        $this->container->singleton(Environment::class, Environment::LOCAL);
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->container->singleton(Environment::class, Environment::PRODUCTION);

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('The application has been down since');
    }

    #[Test]
    public function console_commands_keep_working_while_down(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('routes')
            ->assertSuccess()
            ->assertContains('/maintenance-mode/page');
    }
}
