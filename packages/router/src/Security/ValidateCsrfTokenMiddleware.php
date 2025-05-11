<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use Tempest\Core\AppConfig;
use Tempest\Core\Priority;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Forbidden;
use Tempest\Router\Exceptions\CsrfException;
use Tempest\Router\Exceptions\CsrfTokenNotFoundException;
use Tempest\Router\Exceptions\InvalidCsrfTokenException;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;
use Tempest\Router\MatchedRoute;
use Tempest\Router\Route;

#[Priority(Priority::FRAMEWORK)]
final readonly class ValidateCsrfTokenMiddleware implements HttpMiddleware
{
    public function __construct(
        private AppConfig $appConfig,
        private CsrfConfig $csrfConfig,
        private CsrfTokenManager $csrfTokenManager,
        private MatchedRoute $matchedRoute,
    ) {}

    /**
     * @throws CsrfException
     */
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $routeArgument = $this->resolveRouteArgument($this->matchedRoute->route);

        if ($this->shouldValidate($routeArgument)) {
            $token = $this->getTokenFromRequest($routeArgument, $request);

            if ($token === null && $this->appConfig->environment->isLocal()) {
                throw new CsrfTokenNotFoundException();
            }

            if (! $this->csrfTokenManager->isTokenValid($token)) {
                if ($this->appConfig->environment->isLocal()) {
                    throw new InvalidCsrfTokenException();
                }

                return new Forbidden();
            }
        }

        return $next($request);
    }

    private function resolveRouteArgument(Route $route): ValidateCsrfToken
    {
        $routeArgument = $route->validateCsrfToken;

        if ($routeArgument instanceof ValidateCsrfToken) {
            return $routeArgument;
        }

        $shouldValidate = is_bool($routeArgument)
            ? $routeArgument
            : $route->method->modifiesState();

        return new ValidateCsrfToken(validate: $shouldValidate);
    }

    private function shouldValidate(ValidateCsrfToken $routeArgument): bool
    {
        if (! $this->csrfConfig->enable) {
            return false;
        }

        return $routeArgument->validate;
    }

    private function getTokenFromRequest(ValidateCsrfToken $routeArgument, Request $request): ?CsrfToken
    {
        $id = $routeArgument->id ?? $this->csrfConfig->tokenId;
        $key = $routeArgument->key ?? $this->csrfConfig->tokenKey;

        $value = $request->get($key);
        if ($value === null) {
            return null;
        }

        return new CsrfToken($id, $value);
    }
}
