<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use Tempest\Container\Singleton;
use Tempest\Http\Request;
use Tempest\Router\Exceptions\CsrfTokenNotFoundException;
use Tempest\Router\Exceptions\InvalidCsrfTokenException;
use Tempest\Router\MatchedRoute;

#[Singleton]
final readonly class CsrfValidator
{
    public function __construct(
        private CsrfConfig $csrfConfig,
        private CsrfTokenManager $csrfTokenManager,
        private MatchedRoute $matchedRoute,
    ) {}

    public function shouldValidate(): bool
    {
        if (! $this->csrfConfig->enable || ! $this->matchedRoute->route->validateCsrfToken->validate) {
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
        $validateCsrfToken = $this->matchedRoute->route->validateCsrfToken;

        $value = match ($validateCsrfToken->type) {
            CsrfFieldType::PARAMETER => $request->get($validateCsrfToken->name),
            CsrfFieldType::HEADER => $request->headers->get($validateCsrfToken->name),
        };

        if ($value === null) {
            throw new CsrfTokenNotFoundException();
        }

        if (! $this->csrfTokenManager->isTokenValid($value)) {
            throw new InvalidCsrfTokenException();
        }
    }
}
