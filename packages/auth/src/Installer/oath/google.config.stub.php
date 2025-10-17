<?php

declare(strict_types=1);

use Tempest\Auth\Installer\oath\GoogleOAuthController;
use Tempest\Auth\OAuth\Config\GoogleOAuthConfig;

return new GoogleOAuthConfig(
    clientId: 'OAUTH_GOOGLE_CLIENT_ID',
    clientSecret: 'OAUTH_GOOGLE_CLIENT_SECRET',
    redirectTo: [GoogleOAuthController::class, 'callback'],
    tag: GoogleOAuthController::class,
);
