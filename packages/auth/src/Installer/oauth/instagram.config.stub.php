<?php

declare(strict_types=1);

use Tempest\Auth\Installer\oauth\OAuthControllerStub;
use Tempest\Auth\OAuth\Config\InstagramOAuthConfig;
use Tempest\Auth\OAuth\SupportedOAuthProvider;

use function Tempest\env;

return new InstagramOAuthConfig(
    clientId: env('OAUTH_INSTAGRAM_CLIENT_ID'),
    clientSecret: env('OAUTH_INSTAGRAM_CLIENT_SECRET'),
    redirectTo: [OAuthControllerStub::class, 'callback'],
    tag: SupportedOAuthProvider::INSTAGRAM,
);
