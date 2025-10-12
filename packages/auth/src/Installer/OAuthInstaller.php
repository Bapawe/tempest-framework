<?php

declare(strict_types=1);

namespace Tempest\Auth\Installer;

use Symfony\Component\Process\Process;
use Tempest\Auth\OAuth\AvailableOAuthProvider;
use Tempest\Auth\OAuth\Config;
use Tempest\Core\Installer;
use Tempest\Core\PublishesFiles;
use Tempest\Reflection\ClassReflector;
use Tempest\Support\Str\ImmutableString;

use function Tempest\root_path;
use function Tempest\src_path;
use function Tempest\Support\arr;
use function Tempest\Support\Arr\to_array;
use function Tempest\Support\Filesystem\read_file;
use function Tempest\Support\Namespace\to_fqcn;

final class OAuthInstaller implements Installer
{
    use PublishesFiles;

    private(set) string $name = 'oauth';

    public function install(): void
    {
        /** @var list<AvailableOAuthProvider> $providers */
        $providers = $this->ask(
            question: 'Please choose an OAuth provider',
            options: AvailableOAuthProvider::cases(),
            multiple: true,
        );

        foreach ($providers as $provider) {
            $controller = $this->publishController($provider);

            $this->publishConfig($provider, $controller);

            $this->publishImports();
        }

        $this->installComposerDependencies(...$providers);

        $providers = arr($providers);

        if ($providers->isNotEmpty()) {
            $installedProviders = $providers
                ->map(fn (AvailableOAuthProvider $provider) => $provider->value)
                ->implode(', ')
                ->toString();

            $publishedFiles = arr($this->publishedFiles)
                ->map(fn (string $file) => "<style=\"fg-green\">→</style> {$file}");

            $this->console->instructions([
                "<strong>OAuth providers ({$installedProviders}) are installed in your project</strong>!",
                PHP_EOL,
                '<strong>Published files</strong>',
                ...$publishedFiles,
            ]);
        }
    }

    public function publishConfig(AvailableOAuthProvider $provider, string|false $controller): void
    {
        $configStub = match ($provider) {
            AvailableOAuthProvider::APPLE => __DIR__ . '/oath/apple.config.stub.php',
            AvailableOAuthProvider::DISCORD => __DIR__ . '/oath/discord.config.stub.php',
            AvailableOAuthProvider::FACEBOOK => __DIR__ . '/oath/facebook.config.stub.php',
            AvailableOAuthProvider::GENERIC => __DIR__ . '/oath/generic.config.stub.php',
            AvailableOAuthProvider::GITHUB => __DIR__ . '/oath/github.config.stub.php',
            AvailableOAuthProvider::GOOGLE => __DIR__ . '/oath/google.config.stub.php',
            AvailableOAuthProvider::INSTAGRAM => __DIR__ . '/oath/instagram.config.stub.php',
            AvailableOAuthProvider::LINKEDIN => __DIR__ . '/oath/linkedin.config.stub.php',
            AvailableOAuthProvider::MICROSOFT => __DIR__ . '/oath/microsoft.config.stub.php',
            AvailableOAuthProvider::SLACK => __DIR__ . '/oath/slack.config.stub.php',
        };

        $this->publish(
            source: $configStub,
            destination: src_path("OAuth/{$provider->value}.config.php"),
            callback: function (string $source, string $destination) use ($controller): void {
                $controllerFqcn = $controller !== false ? to_fqcn($controller, root: root_path()) : '';

                $this->update($destination, function (ImmutableString $contents) use ($controllerFqcn): ImmutableString {
                    return $contents->replace("'{REDIRECT_TO}'", "[{$controllerFqcn}::class, 'redirect']");
                });

                \Tempest\Support\str(read_file($destination))
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

                                    return $contents->append(PHP_EOL . "{$envValueName}=");
                                },
                                ignoreNonExisting: true,
                            );
                        }
                    });
            },
        );
    }

    public function publishController(AvailableOAuthProvider $provider): string|false
    {
        $configFqcn = match ($provider) {
            AvailableOAuthProvider::APPLE => Config\AppleOAuthConfig::class,
            AvailableOAuthProvider::DISCORD => Config\DiscordOAuthConfig::class,
            AvailableOAuthProvider::FACEBOOK => Config\FacebookOAuthConfig::class,
            AvailableOAuthProvider::GENERIC => Config\GenericOAuthConfig::class,
            AvailableOAuthProvider::GITHUB => Config\GitHubOAuthConfig::class,
            AvailableOAuthProvider::GOOGLE => Config\GoogleOAuthConfig::class,
            AvailableOAuthProvider::INSTAGRAM => Config\InstagramOAuthConfig::class,
            AvailableOAuthProvider::LINKEDIN => Config\LinkedInOAuthConfig::class,
            AvailableOAuthProvider::MICROSOFT => Config\MicrosoftOAuthConfig::class,
            AvailableOAuthProvider::SLACK => Config\SlackOAuthConfig::class,
        };

        $filePrefix = \Tempest\Support\str(new ClassReflector($configFqcn)->getShortName())
            ->stripEnd('OAuthConfig')
            ->toString();

        return $this->publish(
            source: __DIR__ . '/oath/OAuthController.stub.php',
            destination: src_path("OAuth/{$filePrefix}Controller.php"),
            callback: function (string $source, string $destination) use ($provider): void {
                $this->update(
                    path: $destination,
                    callback: fn (ImmutableString $contents): ImmutableString => $contents->replace(
                        search: ['{ROUTE}', '{COLUMN_PREFIX}'],
                        replace: [$provider->value, $provider->value],
                    ),
                );
            },
        );
    }

    private function installComposerDependencies(AvailableOAuthProvider ...$providers): void
    {
        $packages = arr($providers)
            ->map(fn (AvailableOAuthProvider $provider) => match ($provider) {
                AvailableOAuthProvider::APPLE => 'patrickbussmann/oauth2-apple',
                AvailableOAuthProvider::DISCORD => 'wohali/oauth2-discord-new',
                AvailableOAuthProvider::FACEBOOK => 'league/oauth2-facebook',
                AvailableOAuthProvider::GITHUB => 'league/oauth2-github',
                AvailableOAuthProvider::GOOGLE => 'league/oauth2-google',
                AvailableOAuthProvider::INSTAGRAM => 'league/oauth2-instagram',
                AvailableOAuthProvider::LINKEDIN => 'league/oauth2-linkedin',
                AvailableOAuthProvider::MICROSOFT => 'stevenmaguire/oauth2-microsoft',
                AvailableOAuthProvider::SLACK => 'adam-paterson/oauth2-slack',
                AvailableOAuthProvider::GENERIC => null,
            })
            ->filter();

        if ($packages->isNotEmpty()) {
            if (! $this->confirm('Install composer dependencies?', default: true)) {
                return;
            }

            $this->task('Installing composer dependencies...', new Process(['composer', 'require', ...$packages], cwd: root_path()));
        }
    }
}
