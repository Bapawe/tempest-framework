<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use Tempest\Container\Singleton;
use Tempest\Http\Session\Session;

use function Tempest\Support\Random\secure_string;

#[Singleton]
final readonly class CsrfTokenManager
{
    public function __construct(
        private Session $session,
        private CsrfConfig $config,
    ) {}

    public function generateToken(): string
    {
        return secure_string(length: $this->config->tokenLength);
    }

    public function getToken(string $id): CsrfToken
    {
        $value = $this->session->get($id);

        if ($value === null) {
            $value = $this->generateToken();

            $this->session->set($id, $value);
        }

        return new CsrfToken($id, $value);
    }

    public function refreshToken(string $id): CsrfToken
    {
        $token = $this->generateToken();

        $this->session->set($this->config->tokenId, $token);

        return new CsrfToken($id, $token);
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        if (! $this->config->enable) {
            return true;
        }

        return hash_equals($this->getToken($token->id)->value, $token->value);
    }
}
