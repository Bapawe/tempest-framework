<?php

declare(strict_types=1);

namespace Tempest\Router;

use Tempest\Core\AppConfig;
use Tempest\Core\Priority;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Forbidden;
use Tempest\Http\Security\CsrfConfig;
use Tempest\Http\Security\CsrfTokenManager;
use Tempest\Router\Exceptions\CsrfTokenNotFoundException;
use Tempest\Router\Exceptions\InvalidCsrfTokenException;

#[Priority(Priority::FRAMEWORK)]
final readonly class ValidateCsrfTokenMiddleware implements HttpMiddleware
{
    public const string HEADER_NAME = 'X-CSRF-Token';

    public const string PARAM_NAME = '_token';

    private const array STATE_MODIFYING_METHODS = [
        Method::PATCH,
        Method::PUT,
        Method::POST,
        Method::DELETE,
    ];

    public function __construct(
        private CsrfConfig $csrfConfig,
        private CsrfTokenManager $csrfTokenManager,
        private AppConfig $appConfig,
    ) {}

    /**
     * @throws CsrfTokenNotFoundException
     * @throws InvalidCsrfTokenException
     */
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if ($this->csrfConfig->enable && $this->isStateModifyingMethod($request)) {
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

    private function isStateModifyingMethod(Request $request): bool
    {
        return in_array($request->method, self::STATE_MODIFYING_METHODS, strict: true);
    }

    private function findTokenInRequest(Request $request): ?string
    {
        return $request->get(self::PARAM_NAME) ?? $request->headers[self::HEADER_NAME] ?? null;
    }
}
