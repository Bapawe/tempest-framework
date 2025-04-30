<?php

declare(strict_types=1);

namespace Tempest\Http\Security;

use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

final class CsrfTokenManagerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): CsrfTokenManager
    {
        return $container->get(GenericCsrfTokenManager::class);
    }
}
