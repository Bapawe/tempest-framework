<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use Tempest\Container\Singleton;
use Tempest\Http\Session\Session;

use function Tempest\Support\Random\secure_string;

#[Singleton]
final readonly class CsrfTokenManager
{
    private const string SESSION_NAMESPACE = 'tempest_csrf';

    public function __construct(
        private Session $session,
        private string $namespace = self::SESSION_NAMESPACE,
    ) {}

    public function generateToken(): string
    {
        return secure_string(length: 40);
    }

    public function getToken(string $id): CsrfToken
    {
        $value = $this->session->get($this->getNamespacedId($id));

        if ($value === null) {
            $value = $this->generateToken();

            $this->session->set($this->getNamespacedId($id), $value);
        }

        return new CsrfToken($id, $value);
    }

    public function refreshToken(string $id): CsrfToken
    {
        $value = $this->generateToken();

        $this->session->set($this->getNamespacedId($id), $value);

        return new CsrfToken($id, $value);
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return hash_equals($this->getToken($token->id)->value, $token->value);
    }

    public function clear(): void
    {
        foreach (array_keys($this->session->all()) as $key) {
            if (str_starts_with($key, $this->namespace . '/')) {
                $this->session->remove($key);
            }
        }
    }

    private function getNamespacedId(string $id): string
    {
        return $this->namespace . '/' . $id;
    }
}
