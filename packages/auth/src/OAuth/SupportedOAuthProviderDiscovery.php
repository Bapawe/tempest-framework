<?php

namespace Tempest\Auth\OAuth;

use Tempest\Auth\AuthConfig;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

use function Tempest\Support\Str\strip_end;

final class SupportedOAuthProviderDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly AuthConfig $authConfig,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if ($class->implements(OAuthConfig::class)) {
            /** @var class-string<OAuthConfig> $classFqcn */
            $classFqcn = $class->getName();

            $this->discoveryItems->add($location, new SupportedOAuthProvider(
                name: strip_end($class->getShortName(), suffix: 'OAuthConfig'),
                configStub: $classFqcn::configStub(),
                controllerStub: $classFqcn::controllerStub(),
                composerPackage: $classFqcn::composerPackage(),
            ));
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $discoveryItem) {
            $this->authConfig->supportedOAuthProviders[$discoveryItem->name] = $discoveryItem;
        }
    }
}
