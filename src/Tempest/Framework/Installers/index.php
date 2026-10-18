<?php

declare(strict_types=1);

use Tempest\Router\HttpApplication;

if (is_file($maintenance = __DIR__ . '/../.tempest/maintenance.php')) {
    require $maintenance;
}

require_once __DIR__ . '/../vendor/autoload.php';

HttpApplication::boot(__DIR__ . '/../')->run();
