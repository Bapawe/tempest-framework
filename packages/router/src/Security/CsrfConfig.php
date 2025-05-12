<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

final class CsrfConfig
{
    public function __construct(
        public bool $enable = true,
        public string $tokenId = 'csrf_token',
        public string $tokenKey = '_token',
    ) {}
}
