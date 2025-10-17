<?php

declare(strict_types=1);

use Tempest\Auth\Installer\oath\InstagramOAuthController;
use Tempest\Auth\OAuth\Config\InstagramOAuthConfig;

return new InstagramOAuthConfig(
    clientId: 'OAUTH_INSTAGRAM_CLIENT_ID',
    clientSecret: 'OAUTH_INSTAGRAM_CLIENT_SECRET',
    redirectTo: [InstagramOAuthController::class, 'callback'],
    tag: InstagramOAuthController::class,
);
