<?php

namespace Tempest\Router;

use Tempest\Router\Security\CsrfRouteArgument;

/** @phpstan-require-implements Route */
trait IsRoute
{
    public readonly array $middleware;

    public readonly CsrfRouteArgument $validateCsrfToken;

    /**
     * @param null|class-string<HttpMiddleware>[] $middleware
     */
    public function __construct(
        public readonly string $uri,
        ?array $middleware = null,
        CsrfRouteArgument|bool|null $validateCsrfToken = null,
    ) {
        $this->middleware = $middleware ?? [];
        $this->validateCsrfToken = $this->resolveCsrfValidation($validateCsrfToken);
    }

    private function resolveCsrfValidation(CsrfRouteArgument|bool|null $validateCsrfToken): CsrfRouteArgument
    {
        if ($validateCsrfToken instanceof CsrfRouteArgument) {
            return $validateCsrfToken;
        }

        $shouldValidate = is_bool($validateCsrfToken)
            ? $validateCsrfToken
            : $this->method->modifiesState();

        return new CsrfRouteArgument(validate: $shouldValidate);
    }
}
