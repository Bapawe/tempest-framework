<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;
use Tempest\Core\Kernel;

final readonly class MaintenanceModeStoreInitializer implements Initializer
{
    #[Singleton]
    public function initialize(Container $container): MaintenanceModeStore
    {
        return new FileMaintenanceModeStore($container->get(Kernel::class));
    }
}
