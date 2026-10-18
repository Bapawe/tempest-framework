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

use function Tempest\Support\Filesystem\read_json;
use function Tempest\Support\Filesystem\write_file;

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
    public function html_requests_get_a_503_error_page_while_enabled(): void
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
    public function json_requests_get_a_503_json_error_while_enabled(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::JSON)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertJson(['message' => 'Service Unavailable']);
    }

    #[Test]
    public function requests_that_accept_neither_html_nor_json_still_get_a_503(): void
    {
        $this->console->call('maintenance:enable --retry=600')->assertSuccess();

        $this->http
            ->as(ContentType::TXT)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertHeaderContains('Retry-After', '600')
            ->assertHeaderContains('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('503 Service Unavailable');
    }

    #[Test]
    public function requests_that_accept_neither_html_nor_json_get_no_retry_after_header_without_a_retry(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->as(ContentType::TXT)
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE)
            ->assertDoesNotHaveHeader('Retry-After');
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
    public function unknown_urls_get_a_503_instead_of_a_404_while_enabled(): void
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
            ->assertContains('Maintenance mode is now disabled.');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk()
            ->assertSee('Handled normally');
    }

    #[Test]
    public function enabling_twice_succeeds_and_says_it_was_already_enabled(): void
    {
        $this->console
            ->call('maintenance:enable')
            ->assertSuccess()
            ->assertContains('Maintenance mode is now enabled.');

        $this->console
            ->call('maintenance:enable')
            ->assertSuccess()
            ->assertContains('Maintenance mode was already enabled, its settings are updated.');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function disabling_while_already_disabled_succeeds_with_a_note(): void
    {
        $this->console
            ->call('maintenance:disable')
            ->assertSuccess()
            ->assertContains('Maintenance mode was not enabled.');

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk();
    }

    #[Test]
    public function enabling_saves_when_maintenance_mode_was_enabled_as_enabled_since(): void
    {
        $this->clock('2025-03-14 09:26:53');

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->assertSame(1_741_944_413, read_json($this->kernel->internalStorage . '/maintenance/state.json')['enabled_since'] ?? null);
    }

    #[Test]
    #[TestWith(['{"enabled_since":'])]
    #[TestWith(['{"retry":600}'])]
    #[TestWith(['{"enabled_since":"yesterday"}'])]
    #[TestWith(['{"enabled_since":"1741944413"}'])]
    public function a_broken_maintenance_state_lets_requests_through(string $json): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        write_file($this->kernel->internalStorage . '/maintenance/state.json', $json);

        $this->http
            ->get('/maintenance-mode/page')
            ->assertOk()
            ->assertSee('Handled normally');
    }

    #[Test]
    public function status_reports_disabled_when_the_maintenance_state_is_broken(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        write_file($this->kernel->internalStorage . '/maintenance/state.json', '{"enabled_since":');

        $this->console
            ->call('maintenance:status')
            ->assertSuccess()
            ->assertContains('Maintenance mode is disabled.');
    }

    #[Test]
    public function disabling_with_a_broken_maintenance_state_removes_the_maintenance_folder(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        write_file($this->kernel->internalStorage . '/maintenance/state.json', '{"enabled_since":');
        write_file($this->kernel->internalStorage . '/maintenance/state.json.tmp', '{"enabled_since":');

        $this->console
            ->call('maintenance:disable')
            ->assertSuccess()
            ->assertContains('Maintenance mode was not enabled.');

        $this->assertDirectoryDoesNotExist($this->kernel->internalStorage . '/maintenance');
    }

    #[Test]
    public function disabling_removes_the_maintenance_folder(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->assertFileExists($this->kernel->internalStorage . '/maintenance/check.php');

        $this->console->call('maintenance:disable')->assertSuccess();

        $this->assertDirectoryDoesNotExist($this->kernel->internalStorage . '/maintenance');
    }

    #[Test]
    #[TestWith(['"/maintenance-mode/page"', Status::SERVICE_UNAVAILABLE])]
    #[TestWith(['[42, ["/maintenance-mode/page"], "/maintenance-mode/page"]', Status::OK])]
    public function excluded_paths_that_are_not_strings_are_skipped(string $excludedPaths, Status $status): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        write_file($this->kernel->internalStorage . '/maintenance/state.json', "{\"enabled_since\":1741944413,\"excluded_paths\":{$excludedPaths}}");

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus($status);
    }

    #[Test]
    public function a_malformed_retry_value_is_ignored(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        write_file($this->kernel->internalStorage . '/maintenance/state.json', '{"enabled_since":1741944413,"retry":"soon"}');

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('Maintenance mode has been enabled since')
            ->assertDoesNotContain('retry');
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
    public function status_reports_disabled_and_succeeds_while_disabled(): void
    {
        $this->console
            ->call('maintenance:status')
            ->assertSuccess()
            ->assertContains('Maintenance mode is disabled.');
    }

    #[Test]
    public function status_reports_enabled_since_when_as_info_and_errors_while_enabled(): void
    {
        $this->clock('2025-03-14 09:26:53');

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('𝓲 // Maintenance mode has been enabled since 2025-03-14 09:26:53 (UTC).');
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
    public function status_leaves_out_the_retry_value_when_none_is_set(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('Maintenance mode has been enabled since')
            ->assertDoesNotContain('retry');
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([-5])]
    public function enabling_with_a_retry_below_one_is_rejected_and_leaves_maintenance_mode_disabled(int $retry): void
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
            ->assertDoesNotContain('retry');
    }

    #[Test]
    public function enabling_again_with_a_retry_keeps_the_original_enabled_since_time(): void
    {
        $clock = $this->clock('2025-03-14 09:26:53');

        $this->console->call('maintenance:enable')->assertSuccess();

        $clock->plus(Duration::hours(2));

        $this->console->call('maintenance:enable --retry=600')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('𝓲 // Maintenance mode has been enabled since 2025-03-14 09:26:53 (UTC).')
            ->assertContains('Clients are told to retry after 600 seconds');
    }

    #[Test]
    public function enabling_again_keeps_the_original_enabled_since_time(): void
    {
        $clock = $this->clock('2025-03-14 09:26:53');

        $this->console->call('maintenance:enable')->assertSuccess();

        $clock->plus(Duration::hours(2));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('𝓲 // Maintenance mode has been enabled since 2025-03-14 09:26:53 (UTC).');
    }

    #[Test]
    public function status_reports_disabled_again_after_disabling(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->console->call('maintenance:disable')->assertSuccess();

        $this->console
            ->call('maintenance:status')
            ->assertSuccess()
            ->assertContains('Maintenance mode is disabled.');
    }

    #[Test]
    public function status_never_asks_for_confirmation_in_production(): void
    {
        $this->container->singleton(Environment::class, Environment::PRODUCTION);

        $this->console
            ->call('maintenance:status')
            ->assertSuccess()
            ->assertContains('Maintenance mode is disabled.');

        $this->container->singleton(Environment::class, Environment::LOCAL);
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->container->singleton(Environment::class, Environment::PRODUCTION);

        $this->console
            ->call('maintenance:status')
            ->assertError()
            ->assertContains('Maintenance mode has been enabled since');
    }

    #[Test]
    public function console_commands_keep_working_while_enabled(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->console
            ->call('routes')
            ->assertSuccess()
            ->assertContains('/maintenance-mode/page');
    }
}
