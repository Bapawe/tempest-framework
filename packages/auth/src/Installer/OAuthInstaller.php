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

        $this->publishStubs(...$providers);

        if ($this->confirm('Install composer dependencies?', default: true)) {
            $this->installComposerDependencies(...$providers);
        }

        if ($this->confirm('Would you like to add the OAuth config variables to your .env file?', default: true)) {
            $this->updateEnv(...$providers);
        }

        $this->console->instructions([
            sprintf(
                '<strong>The selected OAuth %s installed in your project</strong>',
                count($providers) > 1 ? 'providers are' : 'provider is',
            ),
            '',
            'Next steps:',
            '1. Update the .env file with your OAuth credentials',
            '2. Review and customize the published files if needed',
            '',
            '<strong>Published files</strong>',
            ...arr($this->publishedFiles)->map(fn (string $file) => '<style="fg-green">→</style> ' . $file),
        ]);
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

    private function publishStubs(SupportedOAuthProvider ...$providers): void
    {
        $userModelPath = src_path('Authentication/User.php');

        $this->publish(
            source: __DIR__ . '/basic-user/UserModel.stub.php',
            destination: $userModelPath,
        );

        foreach ($providers as $provider) {
            $this->publishController($provider, userModelFqcn: to_fqcn($userModelPath, root: root_path()));

            $this->publishConfig($provider);

            $this->publishImports();
        }
    }

    private function publishConfig(SupportedOAuthProvider $provider): void
    {
        $name = strtolower($provider->name);
        $source = __DIR__ . "/../Installer/oauth/{$name}.config.stub.php";

        $this->publish(
            source: $source,
            destination: src_path("Authentication/OAuth/{$name}.config.php"),
        );
    }

    private function publishController(SupportedOAuthProvider $provider, string $userModelFqcn): void
    {
        $fileName = str($provider->value)
            ->classBasename()
            ->replace('Provider', '')
            ->append('Controller.php')
            ->toString();

        $this->publish(
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

    private function installComposerDependencies(SupportedOAuthProvider ...$providers): void
    {
        $packages = arr($providers)
            ->map(fn (SupportedOAuthProvider $provider) => $provider->composerPackage())
            ->filter();

        if ($packages->isNotEmpty()) {
            $this->task(
                label: "Installing composer dependencies {$packages->implode(', ')}",
                handler: new Process(['composer', 'require', ...$packages], cwd: root_path()),
            );
        }
    }

    private function updateEnv(SupportedOAuthProvider ...$providers): void
    {
        foreach ($providers as $provider) {
            $name = strtolower($provider->name);
            $configPath = __DIR__ . "/../Installer/oauth/{$name}.config.stub.php";

            try {
                $publishedConfigContent = str(read_file($configPath));
            } catch (PathWasNotFound|PathWasNotReadable $e) {
                $this->error($e->getMessage());

                continue;
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
    }
}
