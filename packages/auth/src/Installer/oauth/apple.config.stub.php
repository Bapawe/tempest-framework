<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\AppleOAuthConfig;

return new AppleOAuthConfig(
    clientId: 'OAUTH_APPLE_CLIENT_ID',
    teamId: 'OAUTH_APPLE_TEAM_ID',
    keyId: 'OAUTH_APPLE_KEY_ID',
    keyFile: 'OAUTH_APPLE_KEY_FILE',
    redirectTo: [\Tempest\Auth\Installer\oauth\OAuthControllerStub::class, 'callback'],
    tag: \Tempest\Auth\OAuth\SupportedOAuthProvider::APPLE,
);
