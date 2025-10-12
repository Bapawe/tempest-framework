<?php

declare(strict_types=1);

namespace Tempest\Auth\Installer;

use Symfony\Component\Process\Process;
use Tempest\Auth\OAuth\Config;
use Tempest\Auth\OAuth\SupportedOAuthProvider;
use Tempest\Core\Installer;
use Tempest\Core\PublishesFiles;
use Tempest\Reflection\ClassReflector;
use Tempest\Support\Arr\ImmutableArray;
use Tempest\Support\Str\ImmutableString;

use function Tempest\root_path;
use function Tempest\src_path;
use function Tempest\Support\arr;
use function Tempest\Support\Filesystem\read_file;
use function Tempest\Support\Namespace\to_fqcn;
use function Tempest\Support\str;

final class OAuthInstaller implements Installer
{
    use PublishesFiles;

    private(set) string $name = 'oauth';

    public function install(): void
    {
        $providers = arr($this->ask(
            question: 'Please choose an OAuth provider',
            options: SupportedOAuthProvider::cases(),
            multiple: true,
        ));

        $providers->each(function (SupportedOAuthProvider $provider) {
            $controller = $this->publishController($provider);

            $this->publishConfig($provider, $controller);

            $this->publishImports();
        });

        $this->installComposerDependencies($providers);

        if ($providers->isNotEmpty()) {
            $installedProviders = $providers
                ->map(fn (SupportedOAuthProvider $provider) => $provider->value)
                ->implode(', ')
                ->toString();

            $publishedFiles = arr($this->publishedFiles)
                ->map(fn (string $file) => "<style=\"fg-green\">→</style> {$file}");

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

    public function publishConfig(SupportedOAuthProvider $provider, string|false $controller): void
    {
        $configStub = match ($provider) {
            SupportedOAuthProvider::APPLE => __DIR__ . '/oath/apple.config.stub.php',
            SupportedOAuthProvider::DISCORD => __DIR__ . '/oath/discord.config.stub.php',
            SupportedOAuthProvider::FACEBOOK => __DIR__ . '/oath/facebook.config.stub.php',
            SupportedOAuthProvider::GENERIC => __DIR__ . '/oath/generic.config.stub.php',
            SupportedOAuthProvider::GITHUB => __DIR__ . '/oath/github.config.stub.php',
            SupportedOAuthProvider::GOOGLE => __DIR__ . '/oath/google.config.stub.php',
            SupportedOAuthProvider::INSTAGRAM => __DIR__ . '/oath/instagram.config.stub.php',
            SupportedOAuthProvider::LINKEDIN => __DIR__ . '/oath/linkedin.config.stub.php',
            SupportedOAuthProvider::MICROSOFT => __DIR__ . '/oath/microsoft.config.stub.php',
            SupportedOAuthProvider::SLACK => __DIR__ . '/oath/slack.config.stub.php',
        };

        $this->publish(
            source: $configStub,
            destination: src_path("OAuth/{$provider->value}.config.php"),
            callback: function (string $source, string $destination) use ($controller): void {
                $controllerFqcn = $controller !== false ? to_fqcn($controller, root: root_path()) : '';

                $this->update($destination, function (ImmutableString $contents) use ($controllerFqcn): ImmutableString {
                    return $contents->replace("'{REDIRECT_TO}'", "[{$controllerFqcn}::class, 'callback']");
                });

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

                                    return $contents->append(PHP_EOL . "{$envValueName}=");
                                },
                                ignoreNonExisting: true,
                            );
                        }
                    });
            },
        );
    }

    public function publishController(SupportedOAuthProvider $provider): string|false
    {
        $configFqcn = match ($provider) {
            SupportedOAuthProvider::APPLE => Config\AppleOAuthConfig::class,
            SupportedOAuthProvider::DISCORD => Config\DiscordOAuthConfig::class,
            SupportedOAuthProvider::FACEBOOK => Config\FacebookOAuthConfig::class,
            SupportedOAuthProvider::GENERIC => Config\GenericOAuthConfig::class,
            SupportedOAuthProvider::GITHUB => Config\GitHubOAuthConfig::class,
            SupportedOAuthProvider::GOOGLE => Config\GoogleOAuthConfig::class,
            SupportedOAuthProvider::INSTAGRAM => Config\InstagramOAuthConfig::class,
            SupportedOAuthProvider::LINKEDIN => Config\LinkedInOAuthConfig::class,
            SupportedOAuthProvider::MICROSOFT => Config\MicrosoftOAuthConfig::class,
            SupportedOAuthProvider::SLACK => Config\SlackOAuthConfig::class,
        };

        $filePrefix = str(new ClassReflector($configFqcn)->getShortName())
            ->stripEnd('OAuthConfig')
            ->toString();

        return $this->publish(
            source: __DIR__ . '/oath/OAuthController.stub.php',
            destination: src_path("OAuth/{$filePrefix}Controller.php"),
            callback: function (string $source, string $destination) use ($provider): void {
                $this->update(
                    path: $destination,
                    callback: fn (ImmutableString $contents): ImmutableString => $contents->replace(
                        search: ['SupportedOAuthProvider::GENERIC', '{ROUTE}', '{COLUMN_PREFIX}'],
                        replace: ["SupportedOAuthProvider::{$provider->name}", $provider->value, $provider->value],
                    ),
                );
            },
        );
    }

    private function installComposerDependencies(ImmutableArray $providers): void
    {
        $packages = $providers
            ->map(fn (SupportedOAuthProvider $provider) => match ($provider) {
                SupportedOAuthProvider::APPLE => 'patrickbussmann/oauth2-apple',
                SupportedOAuthProvider::DISCORD => 'wohali/oauth2-discord-new',
                SupportedOAuthProvider::FACEBOOK => 'league/oauth2-facebook',
                SupportedOAuthProvider::GITHUB => 'league/oauth2-github',
                SupportedOAuthProvider::GOOGLE => 'league/oauth2-google',
                SupportedOAuthProvider::INSTAGRAM => 'league/oauth2-instagram',
                SupportedOAuthProvider::LINKEDIN => 'league/oauth2-linkedin',
                SupportedOAuthProvider::MICROSOFT => 'stevenmaguire/oauth2-microsoft',
                SupportedOAuthProvider::SLACK => 'adam-paterson/oauth2-slack',
                SupportedOAuthProvider::GENERIC => null,
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
