<?php

declare(strict_types=1);

namespace Tempest\Router;

use Attribute;
use Tempest\Http\Method;
use Tempest\Router\Security\CsrfRouteArgument;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final readonly class Head implements Route
{
    public Method $method;

    /**
     * @param class-string<HttpMiddleware>[] $middleware
     */
    public function __construct(
        public string $uri,
        public array $middleware = [],
        public CsrfRouteArgument $validateCsrfToken = new CsrfRouteArgument(validate: false),
    ) {
        $this->method = Method::HEAD;
    }
}
