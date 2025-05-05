<?php

declare(strict_types=1);

namespace Tempest\Router;

use Tempest\Core\Priority;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Security\CsrfTokenManager;

#[SkipDiscovery]
#[Priority(Priority::FRAMEWORK)]
final readonly class ValidateCsrfTokenMiddleware implements HttpMiddleware
{
    public const string HEADER_NAME = 'X-CSRF-Token';

    public const string PARAM_NAME = '_token';

    public function __construct(
        private CsrfTokenManager $csrfTokenManager,
    ) {}

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        $value = $this->findTokenInRequest($request);
        if ($value === null) {
            throw new \RuntimeException('CSRF token not found');
        }

        if (! $this->csrfTokenManager->isTokenValid($value)) {
            throw new \RuntimeException('Invalid CSRF token');
        }

        return $next($request);
    }

    private function findTokenInRequest(Request $request): ?string
    {
        return $request->get(self::PARAM_NAME) ?? $request->headers[self::HEADER_NAME] ?? null;
    }
}
