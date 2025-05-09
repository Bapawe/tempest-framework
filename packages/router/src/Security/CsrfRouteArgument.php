<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

final readonly class CsrfRouteArgument
{
    public function __construct(
        public bool $validate,
        public string $requestParam = '_token',
        public string $requestHeader = 'X-CSRF-Token',
    ) {}
}
