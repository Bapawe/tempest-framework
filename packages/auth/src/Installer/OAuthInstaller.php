<?php

declare(strict_types=1);

namespace Tempest\Auth\Installer;

use Symfony\Component\Process\Process;
use Tempest\Auth\AuthConfig;
use Tempest\Auth\OAuth\SupportedOAuthProvider;
use Tempest\Core\Installer;
use Tempest\Core\PublishesFiles;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Support\Filesystem\Exceptions\PathWasNotFound;
use Tempest\Support\Filesystem\Exceptions\PathWasNotReadable;
use Tempest\Support\Str\ImmutableString;

use function Tempest\root_path;
use function Tempest\src_path;
use function Tempest\Support\arr;
use function Tempest\Support\Filesystem\read_file;
use function Tempest\Support\str;

final class OAuthInstaller implements Installer
{
    use PublishesFiles;

    private(set) string $name = 'oauth';

    public function __construct(
        private readonly AuthConfig $authConfig,
    ) {}

    public function install(): void
    {
        $providers = arr($this->ask(
            question: 'Please choose an OAuth provider',
            options: array_map(fn (SupportedOAuthProvider $provider) => $provider->name, $this->authConfig->supportedOAuthProviders),
            multiple: true,
        ))->map(fn (string $name) => $this->authConfig->supportedOAuthProviders[$name]);

        $providers->each(function (SupportedOAuthProvider $provider) {
            $this->publishController($provider);

            $this->publishConfig($provider);

            $this->publishImports();
        });

        $this->installComposerDependencies($providers);

        if ($providers->isNotEmpty()) {
            $installedProviders = $providers
                ->map(fn (SupportedOAuthProvider $provider) => $provider->name)
                ->implode(', ')
                ->toString();

            $publishedFiles = arr($this->publishedFiles)
                ->map(fn (string $file) => '<style="fg-green">→</style>' . $file);

            $this->console->instructions([
                "<strong>OAuth providers ({$installedProviders}) are installed in your project</strong>",
                PHP_EOL,
                'Add the OAuth provider config values to your .env file and validate the published controllers.',
                PHP_EOL,
                '<strong>Published files</strong>',
                ...$publishedFiles,
            ]);
        }
    }

    public function publishConfig(SupportedOAuthProvider $provider): void
    {
        $this->publish(
            source: $provider->configStub,
            destination: src_path('OAuth/' . strtolower($provider->name) . '.config.php'),
            callback: fn (string $source, string $destination) => $this->updateEnv($destination),
        );
    }

    public function publishController(SupportedOAuthProvider $provider): string|false
    {
        return $this->publish(
            source: $provider->controllerStub,
            destination: src_path("OAuth/{$provider->name}OAuthController.php"),
        );
    }

    private function installComposerDependencies(ImmutableArray $providers): void
    {
        $packages = $providers
            ->map(fn (SupportedOAuthProvider $provider) => $provider->composerPackage)
            ->filter();

        if ($packages->isNotEmpty()) {
            if (! $this->confirm('Install composer dependencies?', default: true)) {
                return;
            }

            $this->task('Installing composer dependencies...', new Process(['composer', 'require', ...$packages], cwd: root_path()));
        }
    }

    /**
     * @param string $destination
     * @return void
     * @throws PathWasNotFound
     * @throws PathWasNotReadable
     */
    private function updateEnv(string $destination): void
    {
        str(read_file($destination))
            ->matchAll("/'OAUTH_[^']*'/")
            ->each(function (array $match) use ($destination) {
                $this->update(
                    path: $destination,
                    callback: fn (ImmutableString $contents): ImmutableString => $contents->replace(
                        $match[0],
                        "\\Tempest\\env({$match[0]})",
                    ),
                );

                foreach ([root_path('.env'), root_path('.env.example')] as $envPath) {
                    $this->update(
                        $envPath,
                        function (ImmutableString $contents) use ($match): ImmutableString {
                            $envValueName = trim($match[0], "'");

                            if ($contents->contains($envValueName)) {
                                return $contents;
                            }

                            return $contents->append(PHP_EOL . $envValueName . '=');
                        },
                        ignoreNonExisting: true,
                    );
                }
            });
    }
}
