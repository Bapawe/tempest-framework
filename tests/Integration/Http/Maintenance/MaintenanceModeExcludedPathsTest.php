<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\Status;
use Tempest\Router\Maintenance\MaintenanceConfig;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;
use Tests\Tempest\Integration\Http\Maintenance\Fixtures\MaintenanceModeExcludedPathsController;

/**
 * @internal
 */
final class MaintenanceModeExcludedPathsTest extends FrameworkIntegrationTestCase
{
    use UsesTemporaryInternalStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http->registerRoute([MaintenanceModeExcludedPathsController::class, 'health']);
        $this->http->registerRoute([MaintenanceModeExcludedPathsController::class, 'webhooks']);
        $this->http->registerRoute([MaintenanceModeExcludedPathsController::class, 'stripe']);
        $this->http->registerRoute([MaintenanceModeExcludedPathsController::class, 'stripeEvents']);
    }

    #[Test]
    public function an_exact_excluded_path_stays_reachable_while_down(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/health']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/health')
            ->assertOk()
            ->assertSee('Healthy');
    }

    #[Test]
    public function the_query_string_is_ignored_when_matching_excluded_paths(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/health']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/health?probe=1')
            ->assertOk()
            ->assertSee('Healthy');
    }

    #[Test]
    public function a_trailing_slash_is_significant_for_exact_excluded_paths(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/health']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/health/')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function a_wildcard_excluded_path_matches_paths_one_level_below_the_prefix(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/webhooks/*']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/webhooks/stripe')
            ->assertOk()
            ->assertSee('Stripe webhook');
    }

    #[Test]
    public function a_wildcard_excluded_path_matches_deeper_paths(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/webhooks/*']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/webhooks/stripe/events')
            ->assertOk()
            ->assertSee('Stripe events webhook');
    }

    #[Test]
    public function a_wildcard_excluded_path_does_not_match_the_bare_prefix(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/webhooks/*']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/webhooks')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);

        $this->http
            ->get('/webhooks/')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function an_unknown_route_under_an_excluded_prefix_returns_a_404_while_down(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/webhooks/*']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/webhooks/does-not-exist')
            ->assertNotFound();
    }

    #[Test]
    public function paths_that_are_not_excluded_still_get_a_503_while_down(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/health', '/webhooks/*']));

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/maintenance-mode/page')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function no_paths_are_excluded_by_default(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/health')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);

        $this->http
            ->get('/webhooks/stripe')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function excluded_paths_added_while_down_apply_after_enabling_again(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/health']));

        $this->http
            ->get('/health')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/health')
            ->assertOk();
    }

    #[Test]
    public function excluded_paths_removed_while_down_stay_reachable_until_enabling_again(): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: ['/health']));
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->container->config(new MaintenanceConfig(excludedPaths: []));

        $this->http
            ->get('/health')
            ->assertOk();

        $this->console->call('maintenance:enable')->assertSuccess();

        $this->http
            ->get('/health')
            ->assertStatus(Status::SERVICE_UNAVAILABLE);
    }
}
