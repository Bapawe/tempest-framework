<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use Tempest\Container\Singleton;
use Tempest\Http\Request;
use Tempest\Router\Exceptions\CsrfTokenNotFoundException;
use Tempest\Router\Exceptions\InvalidCsrfTokenException;
use Tempest\Router\MatchedRoute;

#[Singleton]
final readonly class CsrfMatchedRouteValidator
{
    public const string HEADER_NAME = 'X-CSRF-Token';

    public const string PARAM_NAME = '_token';

    public function __construct(
        private CsrfConfig $csrfConfig,
        private CsrfTokenManager $csrfTokenManager,
        private MatchedRoute $matchedRoute,
    ) {}

    public function shouldValidate(): bool
    {
        if (! $this->csrfConfig->enable || ! $this->matchedRoute->route->validateCsrfToken) {
            return false;
        }

        return true;
    }

    /**
     * @throws CsrfTokenNotFoundException
     * @throws InvalidCsrfTokenException
     */
    public function validate(Request $request): void
    {
        $value = $request->get(self::PARAM_NAME) ?? $request->headers[self::HEADER_NAME] ?? null;

        if ($value === null) {
            throw new CsrfTokenNotFoundException();
        }

        if (! $this->csrfTokenManager->isTokenValid($value)) {
            throw new InvalidCsrfTokenException();
        }
    }
}
