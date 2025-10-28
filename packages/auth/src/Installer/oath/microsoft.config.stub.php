<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\MicrosoftOAuthConfig;

return new MicrosoftOAuthConfig(
    clientId: 'OAUTH_MICROSOFT_CLIENT_ID',
    clientSecret: 'OAUTH_MICROSOFT_CLIENT_SECRET',
    redirectTo: [\Tempest\Auth\Installer\oath\OAuthControllerStub::class, 'callback'],
    tag: \Tempest\Auth\OAuth\SupportedOAuthProvider::MICROSOFT,
);
