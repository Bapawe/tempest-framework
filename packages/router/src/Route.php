<?php

declare(strict_types=1);

namespace Tempest\Router;

use Tempest\Http\Method;
use Tempest\Router\Security\ValidateCsrfToken;

interface Route
{
    public Method $method {
        get;
    }

    public string $uri {
        get;
    }

    /** @var class-string<HttpMiddleware>[]  */
    public array $middleware {
        get;
    }

    public ValidateCsrfToken|bool|null $validateCsrfToken {
        get;
    }
}
