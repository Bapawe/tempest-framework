<?php

namespace Tempest\Router;

use Tempest\Router\Security\ValidateCsrfToken;

/** @phpstan-require-implements Route */
trait IsRoute
{
    /** @var class-string<HttpMiddleware>[] */
    public array $middleware;

    /**
     * @param null|class-string<HttpMiddleware>[] $middleware
     */
    public function __construct(
        public readonly string $uri,
        ?array $middleware = null,
        public readonly ValidateCsrfToken|bool|null $validateCsrfToken = null,
    ) {
        $this->middleware = $middleware ?? [];
    }
}
