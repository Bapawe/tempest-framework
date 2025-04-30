<?php

declare(strict_types=1);

namespace Tempest\Http\Security;

use Tempest\Http\Request;
use Tempest\Http\Session\Session;

use function Tempest\Support\Random\secure_string;

final readonly class GenericCsrfTokenManager implements CsrfTokenManager
{
    public const string TOKEN_NAME = 'csrf_token';

    public function __construct(
        private Session $session,
    ) {}

    public function getToken(): string
    {
        $token = $this->session->get(self::TOKEN_NAME);
        if ($token !== null) {
            return $token;
        }

        $this->refreshToken();

        return $this->session->get(self::TOKEN_NAME);
    }

    public function refreshToken(): void
    {
        $this->session->set(self::TOKEN_NAME, secure_string(length: 40));
    }

    public function isTokenValid(#[\SensitiveParameter]  $token): bool
    {
        return hash_equals($this->getToken(), $token);
    }

    public function findTokenInRequest(Request $request): ?string
    {
        return $request->get(self::TOKEN_NAME);
    }
}
