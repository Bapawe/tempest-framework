<?php

declare(strict_types=1);

namespace Tempest\Http\Security;

use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;
use Tempest\Http\Session\Session;

final class CsrfTokenManagerInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): CsrfTokenManager
    {
        return new CsrfTokenManager($container->get(Session::class), $container->get(CsrfConfig::class));
    }
}
