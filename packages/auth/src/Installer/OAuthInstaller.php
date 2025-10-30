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
        $providers = $this->ask(
            question: 'Please choose an OAuth provider',
            options: SupportedOAuthProvider::cases(),
            multiple: true,
        );

        foreach ($providers as $provider) {
            $this->publishController($provider);

            $publishedConfig = $this->publishConfig($provider);
            if ($publishedConfig !== false && $this->confirm('Update .env file?', default: true)) {
                $this->updateEnv($publishedConfig);
            }

            $this->publishImports();

            $this->installComposerDependencies($provider);
        }

        $installedProviders = arr($providers)
            ->map(fn (SupportedOAuthProvider $provider) => $this->getProviderName($provider))
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

    private function publishConfig(SupportedOAuthProvider $provider): false|string
    {
        $providerName = $this->getProviderName($provider);
        $source = __DIR__ . "/../Installer/oauth/{$providerName}.config.stub.php";

        return $this->publish(
            source: $source,
            destination: src_path("OAuth/{$providerName}.config.php"),
        );
    }

    private function publishController(SupportedOAuthProvider $provider): false|string
    {
        $fileName = str($provider->value)
            ->classBasename()
            ->replace('Provider', '')
            ->append('Controller.php')
            ->toString();

        return $this->publish(
            source: __DIR__ . '/oauth/OAuthControllerStub.php',
            destination: src_path("OAuth/{$fileName}"),
            callback: function (string $source, string $destination) use ($provider) {
                $providerName = $this->getProviderName($provider);

                $this->update(
                    $destination,
                    fn (ImmutableString $contents) => $contents->replace(
                        search: ['tag_name', 'redirect-route', 'callback-route', 'user-model-fqcn', 'provider_db_column'],
                        replace: [
                            '\\' . $provider::class . '::' . $provider->name,
                            "/auth/{$providerName}",
                            "/auth/{$providerName}/callback",
                            to_fqcn($this->ask('Model file path'), root_path()),
                            "{$providerName}_id",
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
        try {
            $publishedConfigContent = str(read_file($configPath));
        } catch (PathWasNotFound|PathWasNotReadable $e) {
            $this->error($e->getMessage());

            return;
        }

        $publishedConfigContent
            ->matchAll("/'OAUTH_[^']*'/")
            ->each(function (array $match) use ($configPath) {
                $this->update(
                    path: $configPath,
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

                            $value = str($envValueName)
                                ->snake()
                                ->lower()
                                ->prepend('your_');

                            return $contents->append(PHP_EOL, "{$envValueName}={$value}");
                        },
                        ignoreNonExisting: true,
                    );
                }
            });
    }

    private function getProviderName(SupportedOAuthProvider $provider): string
    {
        return strtolower($provider->name);
    }
}
