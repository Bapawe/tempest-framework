<?php

namespace Tempest\Router;

use Tempest\Http\Method;
use Tempest\Router\Security\CsrfRouteArgument;

/** @phpstan-require-implements Route */
trait IsRoute
{
    public readonly array $middleware;
    public readonly CsrfRouteArgument $validateCsrfToken;

    public const array STATE_MODIFYING_METHODS = [
        Method::PATCH,
        Method::PUT,
        Method::POST,
        Method::DELETE,
    ];

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

    public function isStateModifyingMethod(): bool
    {
        return in_array(
            needle: $this->method,
            haystack: self::STATE_MODIFYING_METHODS,
            strict: true,
        );
    }

    private function resolveCsrfValidation(CsrfRouteArgument|bool|null $validateCsrfToken): CsrfRouteArgument
    {
        if ($validateCsrfToken instanceof CsrfRouteArgument) {
            return $validateCsrfToken;
        }

        $shouldValidate = is_bool($validateCsrfToken)
            ? $validateCsrfToken
            : $this->isStateModifyingMethod();

        return new CsrfRouteArgument(validate: $shouldValidate);
    }
}
