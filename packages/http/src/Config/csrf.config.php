<?php

declare(strict_types=1);

use Tempest\Http\Security\CsrfConfig;

use function Tempest\env;

return new CsrfConfig(
    enable: env('CSRF') ?? true,
    tokenName: 'csrf_token',
    tokenLength: 40,
);
