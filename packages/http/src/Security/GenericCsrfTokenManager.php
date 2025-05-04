<?php

declare(strict_types=1);

namespace Tempest\Http\Security;

use SensitiveParameter;
use Tempest\Http\Session\Session;

use function Tempest\Support\Random\secure_string;

final readonly class GenericCsrfTokenManager implements CsrfTokenManager
{
    public const string TOKEN_ID = 'csrf_token';

    public function __construct(
        private Session $session,
    ) {}

    public function generateToken(): string
    {
        ll('Generating CSRF token');
        return secure_string(length: 40);
    }

    public function getToken(): string
    {
        $token = $this->session->get(self::TOKEN_ID);

        if ($token === null) {
            $token = $this->generateToken();

            $this->session->set(self::TOKEN_ID, $token);
        }

        return $token;
    }

    public function refreshToken(): string
    {
        $token = $this->generateToken();

        $this->session->set(self::TOKEN_ID, $token);

        return $token;
    }

    public function isTokenValid(#[SensitiveParameter] string $token): bool
    {
        return hash_equals($this->getToken(), $token);
    }
}
