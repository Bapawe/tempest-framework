<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\InstagramOAuthConfig;

return new InstagramOAuthConfig(
    clientId: 'OAUTH_INSTAGRAM_CLIENT_ID',
    clientSecret: 'OAUTH_INSTAGRAM_CLIENT_SECRET',
    redirectTo: [\Tempest\Auth\Installer\oauth\OAuthControllerStub::class, 'callback'],
    tag: \Tempest\Auth\OAuth\SupportedOAuthProvider::INSTAGRAM,
);
