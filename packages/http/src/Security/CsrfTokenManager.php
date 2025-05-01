<?php

namespace Tempest\Http\Security;

use Tempest\Http\Request;

interface CsrfTokenManager
{
    public function getToken(): string;

    public function refreshToken(): string;

    public function isTokenValid(string $token): bool;

    public function findTokenInRequest(Request $request): ?string;
}
