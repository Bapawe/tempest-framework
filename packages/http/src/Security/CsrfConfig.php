<?php

declare(strict_types=1);

namespace Tempest\Http\Security;

final class CsrfConfig
{
    public function __construct(
        public bool $enable,
        public string $tokenName,
        public int $tokenLength,
    ) {}
}
