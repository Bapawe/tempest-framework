<?php

declare(strict_types=1);

namespace Tempest\Auth\Installer;

use Symfony\Component\Process\Process;
use Tempest\Auth\OAuth\SupportedOAuthProvider;
use Tempest\Core\Installer;
use Tempest\Core\PublishesFiles;
use Tempest\Support\Filesystem\Exceptions\PathWasNotFound;
use Tempest\Support\Filesystem\Exceptions\PathWasNotReadable;
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
        $providers = $this->getProviders();

        if (count($providers) === 0) {
            return;
        }

        $userModelFqcn = $this->getUserModelFqcn();

        foreach ($providers as $provider) {
            $this->publishController($provider, $userModelFqcn);

            $publishedConfig = $this->publishConfig($provider);
            if ($publishedConfig !== false) {
                $this->updateEnv($publishedConfig);
            }

            $this->installComposerDependencies($provider);
        }

        $this->publishImports();

        $installedProviders = arr($providers)
            ->map(fn (SupportedOAuthProvider $provider) => match ($provider) {
                SupportedOAuthProvider::APPLE => 'Apple',
                SupportedOAuthProvider::DISCORD => 'Discord',
                SupportedOAuthProvider::FACEBOOK => 'Facebook',
                SupportedOAuthProvider::GENERIC => 'Generic',
                SupportedOAuthProvider::GITHUB => 'GitHub',
                SupportedOAuthProvider::GOOGLE => 'Google',
                SupportedOAuthProvider::INSTAGRAM => 'Instagram',
                SupportedOAuthProvider::LINKEDIN => 'LinkedIn',
                SupportedOAuthProvider::MICROSOFT => 'Microsoft',
                SupportedOAuthProvider::SLACK => 'Slack',
            })
            ->implode(', ')
            ->toString();

        $publishedFiles = arr($this->publishedFiles)
            ->map(fn (string $file) => '<style="fg-green">→</style>' . $file);

        $this->console->instructions([
            sprintf(
                '<strong>OAuth %s (%s) %s installed in your project</strong>',
                count($providers) > 1 ? 'providers' : 'provider',
                $installedProviders,
                count($providers) > 1 ? 'are' : 'is',
            ),
            '',
            'Next steps:',
            '1. Update the .env file with your OAuth credentials',
            '2. Review and customize the published files if needed',
            '',
            '<strong>Published files</strong>',
            ...$publishedFiles,
        ]);
    }

    private function publishConfig(SupportedOAuthProvider $provider): false|string
    {
        $name = strtolower($provider->name);
        $source = __DIR__ . "/../Installer/oauth/{$name}.config.stub.php";

        return $this->publish(
            source: $source,
            destination: src_path("Authentication/OAuth/{$name}.config.php"),
        );
    }

    private function publishController(SupportedOAuthProvider $provider, string $userModelFqcn): false|string
    {
        $fileName = str($provider->value)
            ->classBasename()
            ->replace('Provider', '')
            ->append('Controller.php')
            ->toString();

        return $this->publish(
            source: __DIR__ . '/oauth/OAuthControllerStub.php',
            destination: src_path("Authentication/OAuth/{$fileName}"),
            callback: function (string $source, string $destination) use ($provider, $userModelFqcn) {
                $name = strtolower($provider->name);

                $this->update(
                    $destination,
                    fn (ImmutableString $contents) => $contents->replace(
                        search: ["'tag_name'", 'redirect-route', 'callback-route', "'user-model-fqcn'", 'provider_db_column'],
                        replace: [
                            '\\' . $provider::class . '::' . $provider->name,
                            "/auth/{$name}",
                            "/auth/{$name}/callback",
                            '\\' . $userModelFqcn . '::class',
                            "{$name}_id",
                        ],
                    ),
                );
            },
        );
    }

    private function installComposerDependencies(SupportedOAuthProvider $provider): void
    {
        $package = $provider->composerPackage();

        if (! $this->confirm("Install composer dependency <em>https://github.com/{$package}</em>?", default: true)) {
            return;
        }

        $this->task("Installing composer dependency {$package}", new Process(['composer', 'require', $package], cwd: root_path()));
    }

    private function updateEnv(string $configPath): void
    {
        if (! $this->confirm('Would you like to add the OAuth config variables to your .env file?', default: true)) {
            return;
        }

        try {
            $publishedConfigContent = str(read_file($configPath));
        } catch (PathWasNotFound|PathWasNotReadable $e) {
            $this->error($e->getMessage());

            return;
        }

        $publishedConfigContent
            ->matchAll("/env\('(OAUTH_[^']*)'\)/", matches: 1)
            ->each(function (array $match) {
                $setting = $match[1];

                $this->update(
                    root_path('.env'),
                    function (ImmutableString $contents) use ($setting): ImmutableString {
                        if ($contents->contains($setting)) {
                            return $contents;
                        }

                        $value = str($setting)
                            ->snake()
                            ->lower()
                            ->prepend('your_');

                        return $contents->append(PHP_EOL, "{$setting}={$value}");
                    },
                    ignoreNonExisting: true,
                );
            });
    }

    /**
     * @return class-string
     */
    private function getUserModelFqcn(): string
    {
        $userModelPath = src_path('Authentication/User.php');

        $this->publish(
            source: __DIR__ . '/basic-user/UserModel.stub.php',
            destination: $userModelPath,
        );

        return to_fqcn($userModelPath, root: root_path());
    }

    /**
     * @return list<SupportedOAuthProvider>
     */
    private function getProviders(): array
    {
        return $this->ask(
            question: 'Please choose an OAuth provider',
            options: SupportedOAuthProvider::cases(),
            multiple: true,
        );
    }
}
