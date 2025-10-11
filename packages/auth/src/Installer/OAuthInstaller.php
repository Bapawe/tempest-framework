<?php

declare(strict_types=1);

namespace Tempest\Auth\Installer;

use Tempest\Auth\OAuth\AvailableOAuthProvider;
use Tempest\Auth\OAuth\Config;
use Tempest\Core\Installer;
use Tempest\Core\PublishesFiles;
use Tempest\Reflection\ClassReflector;
use Tempest\Support\Str\ImmutableString;

use function Tempest\root_path;
use function Tempest\src_path;
use function Tempest\Support\Filesystem\read_file;

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
            $this->publishConfig($provider);

            $this->publishController($provider);

            $this->publishImports();
        }

        // add empty values in .env.example and .env
        // install the required league dependencies
    }

    public function publishConfig(AvailableOAuthProvider $provider): void
    {
        $configStub = match ($provider) {
            AvailableOAuthProvider::APPLE => __DIR__ . '/oath/apple.config.stub.php',
            AvailableOAuthProvider::DISCORD => __DIR__ . '/oath/discord.config.stub.php',
            AvailableOAuthProvider::FACEBOOK => __DIR__ . '/oath/facebook.config.stub.php',
            AvailableOAuthProvider::GENERIC => __DIR__ . '/oath/generic.config.stub.php',
            AvailableOAuthProvider::GITHUB => __DIR__ . '/oath/github.config.stub.php',
            AvailableOAuthProvider::GOOGLE => __DIR__ . '/oauth/google.config.stub.php',
            AvailableOAuthProvider::INSTAGRAM => __DIR__ . '/oath/instagram.config.stub.php',
            AvailableOAuthProvider::LINKEDIN => __DIR__ . '/oath/linkedin.config.stub.php',
            AvailableOAuthProvider::MICROSOFT => __DIR__ . '/oath/microsoft.config.stub.php',
            AvailableOAuthProvider::SLACK => __DIR__ . '/oath/slack.config.stub.php',
        };

        $this->publish(
            source: $configStub,
            destination: src_path("OAuth/{$provider->value}.config.php"),
            callback: function (string $source, string $destination) {
                $matches = \Tempest\Support\str(read_file($destination))->matchAll("/'OAUTH_[^']*'/");

                $matches->each(function (array $match) use ($destination) {
                    $this->update(
                        path: $destination,
                        callback: fn (ImmutableString $contents): ImmutableString => $contents->replace(
                            $match[0],
                            "\\Tempest\\env({$match[0]})",
                        ),
                    );
                });

                $matches->each(function (array $match) {
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

    public function publishController(AvailableOAuthProvider $provider): void
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

        $this->publish(
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
}
