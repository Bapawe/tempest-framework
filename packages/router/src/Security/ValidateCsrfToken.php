<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

final readonly class ValidateCsrfToken
{
    public function __construct(
        public bool $validate,
        public ?string $id = null,
        public ?string $key = null,
    ) {}
}
