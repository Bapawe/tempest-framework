<?php

namespace Tempest\Auth\OAuth;

final readonly class SupportedOAuthProvider
{
    public function __construct(
        public string $name,
        public string $configStub,
        public string $controllerStub,
        public ?string $composerPackage,
    ) {}
}
