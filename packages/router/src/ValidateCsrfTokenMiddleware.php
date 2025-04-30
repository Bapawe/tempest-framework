<?php

declare(strict_types=1);

namespace Tempest\Router;

use Tempest\Core\AppConfig;
use Tempest\Core\Priority;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Security\CsrfTokenManager;

#[Priority(Priority::FRAMEWORK)]
final readonly class ValidateCsrfTokenMiddleware implements HttpMiddleware
{
    public function __construct(
        private CsrfTokenManager $tokenManager,
        private AppConfig $appConfig,
    ) {}

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if (! $this->appConfig->environment->isTesting()) {
            $token = $this->tokenManager->findTokenInRequest($request);
            if ($token === null) {
                throw new \RuntimeException('CSRF token not found');
            }

            if ($this->tokenManager->isTokenValid($token)) {
                throw new \RuntimeException('Invalid CSRF token');
            }
        }

        return $next($request);
    }
}
