<?php

declare(strict_types=1);

namespace Tempest\Http\Security;

use SensitiveParameter;
use Tempest\Http\Session\Session;

use function Tempest\Support\Random\secure_string;

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

    public function getToken(): string
    {
        $token = $this->session->get($this->config->tokenName);

        if ($token === null) {
            $token = $this->generateToken();

            $this->session->set($this->config->tokenName, $token);
        }

        return $token;
    }

    public function refreshToken(): string
    {
        $token = $this->generateToken();

        $this->session->set($this->config->tokenName, $token);

        return $token;
    }

    public function isTokenValid(#[SensitiveParameter] string $token): bool
    {
        if (! $this->config->enable) {
            return true;
        }

        return hash_equals($this->getToken(), $token);
    }
}
