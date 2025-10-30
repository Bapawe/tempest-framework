<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\GoogleOAuthConfig;

return new GoogleOAuthConfig(
    clientId: 'OAUTH_GOOGLE_CLIENT_ID',
    clientSecret: 'OAUTH_GOOGLE_CLIENT_SECRET',
    redirectTo: [\Tempest\Auth\Installer\oath\OAuthControllerStub::class, 'callback'],
    tag: \Tempest\Auth\OAuth\SupportedOAuthProvider::GOOGLE,
);
