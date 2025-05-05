<?php

declare(strict_types=1);

namespace Tempest\Router;

use Tempest\Core\AppConfig;
use Tempest\Core\Priority;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Forbidden;
use Tempest\Http\Security\CsrfConfig;
use Tempest\Http\Security\CsrfTokenManager;
use Tempest\Router\Exceptions\CsrfTokenNotFoundException;
use Tempest\Router\Exceptions\InvalidCsrfTokenException;

#[SkipDiscovery]
#[Priority(Priority::FRAMEWORK)]
final readonly class ValidateCsrfTokenMiddleware implements HttpMiddleware
{
    public const string HEADER_NAME = 'X-CSRF-Token';

    public const string PARAM_NAME = '_token';

    public function __construct(
        private CsrfConfig $config,
        private CsrfTokenManager $csrfTokenManager,
        private AppConfig $appConfig,
    ) {}

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if ($this->config->enable) {
            $value = $this->findTokenInRequest($request);
            if ($value === null && $this->appConfig->environment->isLocal()) {
                throw new CsrfTokenNotFoundException();
            }

            if ($value === null || ! $this->csrfTokenManager->isTokenValid($value)) {
                if ($this->appConfig->environment->isLocal()) {
                    throw new InvalidCsrfTokenException();
                }

                return new Forbidden();
            }
        }

        return $next($request);
    }

    private function findTokenInRequest(Request $request): ?string
    {
        return $request->get(self::PARAM_NAME) ?? $request->headers[self::HEADER_NAME] ?? null;
    }
}
