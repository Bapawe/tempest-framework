<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

final readonly class CsrfRouteArgument
{
    public function __construct(
        public bool $validate,
        public CsrfFieldType $type = CsrfFieldType::PARAMETER,
        public string $name = CsrfFieldType::PARAMETER->value,
    ) {}
}
