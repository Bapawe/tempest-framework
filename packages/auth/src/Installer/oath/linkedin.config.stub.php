<?php

declare(strict_types=1);

use Tempest\Auth\Installer\oath\LinkedInOAuthController;
use Tempest\Auth\OAuth\Config\LinkedInOAuthConfig;

return new LinkedInOAuthConfig(
    clientId: 'OAUTH_LINKEDIN_CLIENT_ID',
    clientSecret: 'OAUTH_LINKEDIN_CLIENT_SECRET',
    redirectTo: [LinkedInOAuthController::class, 'callback'],
    tag: LinkedInOAuthController::class,
);
