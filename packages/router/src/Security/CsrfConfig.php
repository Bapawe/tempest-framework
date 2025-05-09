<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

final class CsrfConfig
{
    public function __construct(
        public bool $enable = true,
        public string $tokenName = 'csrf_token',
        public int $tokenLength = 40,
    ) {}
}
