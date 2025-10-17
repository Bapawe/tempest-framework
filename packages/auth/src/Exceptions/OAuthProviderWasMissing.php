<?php

declare(strict_types=1);

namespace Tempest\Auth\Exceptions;

use Exception;

final class OAuthProviderWasMissing extends Exception implements AuthenticationException
{
    public function __construct(
        private readonly string $providerName,
        private readonly string $composerPackage,
    ) {
        parent::__construct(sprintf('The `%s` OAuth provider is missing. Install it using `composer require %s`.', $this->providerName, $this->composerPackage));
    }
}
