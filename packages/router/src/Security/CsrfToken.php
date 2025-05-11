<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use SensitiveParameter;

final readonly class CsrfToken
{
    public function __construct(
        public string $id,
        #[SensitiveParameter]
        public string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
