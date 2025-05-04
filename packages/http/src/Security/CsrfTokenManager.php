<?php

namespace Tempest\Http\Security;

use SensitiveParameter;

interface CsrfTokenManager
{
    public function generateToken(): string;

    public function getToken(): string;

    public function refreshToken(): string;

    public function isTokenValid(#[SensitiveParameter] string $token): bool;
}
