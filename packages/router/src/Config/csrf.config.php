<?php

declare(strict_types=1);

use Tempest\Router\Security\CsrfConfig;

use function Tempest\env;

return new CsrfConfig(
    enable: env('CSRF') ?? true,
);
