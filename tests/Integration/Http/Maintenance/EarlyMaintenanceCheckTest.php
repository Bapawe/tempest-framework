<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;
use Tempest\Router\Maintenance\MaintenanceConfig;
use Tempest\Router\RouteConfig;
use Tempest\Support\Priority;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;
use Tests\Tempest\Integration\Http\Maintenance\Fixtures\BrokenMaintenancePageRenderer;
use Tests\Tempest\Integration\Http\Maintenance\Fixtures\MaintenancePageRenderer;

use function Tempest\Support\Filesystem\copy_file;
use function Tempest\Support\Filesystem\create_directory;
use function Tempest\Support\Filesystem\create_temporary_directory;
use function Tempest\Support\Filesystem\delete_directory;
use function Tempest\Support\Filesystem\delete_file;
use function Tempest\Support\Filesystem\write_file;

/**
 * Serves a real `public/index.php` with PHP's built-in server, so requests go through the early maintenance
 * check before the autoloader. The `vendor/autoload.php` next to it is a stand-in: it either prints "booted",
 * and exits like an application that boots normally, or throws, like a `vendor/` that's being reinstalled.
 *
 * @internal
 */
final class EarlyMaintenanceCheckTest extends FrameworkIntegrationTestCase
{
    private const string INSTALLER_INDEX = __DIR__ . '/../../../../src/Tempest/Framework/Installers/index.php';

    private const string REPOSITORY_INDEX = __DIR__ . '/../../../../public/index.php';

    private string $applicationRoot;

    private ?Process $server = null;

    private string $baseUri;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationRoot = create_temporary_directory('tempest-early-maintenance-');
        create_directory($this->applicationRoot . '/.tempest');
        $this->serves(self::INSTALLER_INDEX);
        $this->bootsNormally();

        $this->kernel->internalStorage = $this->applicationRoot . '/.tempest';
    }

    #[After(priority: 1)]
    protected function stopServer(): void
    {
        $this->server?->stop();
        delete_directory($this->applicationRoot);
    }

    #[Test]
    #[TestWith([self::INSTALLER_INDEX])]
    #[TestWith([self::REPOSITORY_INDEX])]
    public function requests_get_a_503_while_down_even_when_vendor_cannot_be_loaded(string $index): void
    {
        $this->serves($index);
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->vendorIsBroken();

        $response = $this->request('/anything');

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function the_503_carries_a_retry_after_header_when_a_retry_is_given(): void
    {
        $this->console->call('maintenance:enable --retry=600')->assertSuccess();
        $this->vendorIsBroken();

        $response = $this->request('/anything');

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('600', $response->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function the_503_carries_no_retry_after_header_without_a_retry(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->vendorIsBroken();

        $response = $this->request('/anything');

        $this->assertSame(503, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Retry-After'));
    }

    #[Test]
    public function html_requests_get_the_rendered_503_page(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->vendorIsBroken();

        $response = $this->request('/anything', ['Accept' => 'text/html']);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('The service is temporarily unavailable', (string) $response->getBody());
    }

    #[Test]
    public function json_requests_get_the_rendered_503_json_error(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->vendorIsBroken();

        $response = $this->request('/anything', ['Accept' => 'application/json']);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Service Unavailable', json_decode((string) $response->getBody(), associative: true)['message'] ?? null);
    }

    #[Test]
    public function a_custom_exception_renderer_renders_the_early_maintenance_page(): void
    {
        $this->container->get(RouteConfig::class)->addExceptionRenderer(MaintenancePageRenderer::class, Priority::HIGH);

        $this->console->call('maintenance:enable')->assertSuccess();
        $this->vendorIsBroken();

        $response = $this->request('/anything', ['Accept' => 'text/html']);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('We will be right back', (string) $response->getBody());
    }

    #[Test]
    #[TestWith(['/health', '/health'])]
    #[TestWith(['/health', '/health?probe=1'])]
    #[TestWith(['/webhooks/*', '/webhooks/stripe'])]
    #[TestWith(['/webhooks/*', '/webhooks/stripe/events'])]
    public function excluded_paths_reach_the_application_while_down(string $pattern, string $path): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: [$pattern]));
        $this->console->call('maintenance:enable')->assertSuccess();

        $response = $this->request($path);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('booted', (string) $response->getBody());
    }

    #[Test]
    #[TestWith(['/health', '/health/'])]
    #[TestWith(['/webhooks/*', '/webhooks'])]
    #[TestWith(['/webhooks/*', '/webhooks/'])]
    public function paths_that_do_not_match_an_excluded_path_get_a_503(string $pattern, string $path): void
    {
        $this->container->config(new MaintenanceConfig(excludedPaths: [$pattern]));
        $this->console->call('maintenance:enable')->assertSuccess();

        $this->assertSame(503, $this->request($path)->getStatusCode());
    }

    #[Test]
    public function requests_reach_the_application_again_after_disabling(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        $this->assertSame(503, $this->request('/anything')->getStatusCode());

        $this->console->call('maintenance:disable')->assertSuccess();
        $response = $this->request('/anything');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('booted', (string) $response->getBody());
    }

    #[Test]
    public function a_leftover_stub_without_maintenance_state_lets_requests_through(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        delete_file($this->applicationRoot . '/.tempest/maintenance.json');

        $response = $this->request('/anything');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('booted', (string) $response->getBody());
    }

    #[Test]
    public function a_corrupt_maintenance_state_lets_requests_through(): void
    {
        $this->console->call('maintenance:enable')->assertSuccess();
        write_file($this->applicationRoot . '/.tempest/maintenance.json', '{"down_since":');

        $response = $this->request('/anything');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('booted', (string) $response->getBody());
    }

    #[Test]
    public function a_maintenance_page_that_fails_to_render_falls_back_to_the_built_in_page(): void
    {
        $this->container->get(RouteConfig::class)->addExceptionRenderer(BrokenMaintenancePageRenderer::class, Priority::HIGH);

        $this->console
            ->call('maintenance:enable')
            ->assertSuccess()
            ->assertContains('The maintenance page could not be rendered')
            ->assertContains('The application is now down');
        $this->vendorIsBroken();

        $html = $this->request('/anything', ['Accept' => 'text/html']);
        $json = $this->request('/anything', ['Accept' => 'application/json']);

        $this->assertSame(503, $html->getStatusCode());
        $this->assertStringContainsString('The service is temporarily unavailable', (string) $html->getBody());
        $this->assertSame(503, $json->getStatusCode());
        $this->assertSame('Service Unavailable', json_decode((string) $json->getBody(), associative: true)['message'] ?? null);
    }

    private function bootsNormally(): void
    {
        write_file($this->applicationRoot . '/vendor/autoload.php', '<?php echo "booted"; exit;');
    }

    private function serves(string $index): void
    {
        copy_file($index, $this->applicationRoot . '/public/index.php', overwrite: true);
    }

    private function vendorIsBroken(): void
    {
        write_file($this->applicationRoot . '/vendor/autoload.php', '<?php throw new RuntimeException("vendor is being reinstalled");');
    }

    /** @param array<string, string> $headers */
    private function request(string $path, array $headers = []): ResponseInterface
    {
        $this->startServer();

        return new Client(['base_uri' => $this->baseUri, 'http_errors' => false])->get($path, ['headers' => $headers]);
    }

    private function startServer(): void
    {
        if ($this->server instanceof Process) {
            return;
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, remote: false);
        fclose($socket);

        $this->baseUri = "http://{$address}";
        $this->server = new Process([PHP_BINARY, '-S', $address, '-t', $this->applicationRoot . '/public', $this->applicationRoot . '/public/index.php']);
        $this->server->start();

        $deadline = microtime(true) + 5;

        while (! @fsockopen('tcp://' . $address)) {
            if (microtime(true) > $deadline) {
                self::fail('The built-in server did not start: ' . $this->server->getErrorOutput());
            }

            usleep(20_000);
        }
    }
}
