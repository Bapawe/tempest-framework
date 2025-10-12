<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\LinkedInOAuthConfig;
use Tempest\Auth\OAuth\SupportedOAuthProvider;

return new LinkedInOAuthConfig(
    clientId: 'OAUTH_LINKEDIN_CLIENT_ID',
    clientSecret: 'OAUTH_LINKEDIN_CLIENT_SECRET',
    redirectTo: '{REDIRECT_TO}',
    tag: SupportedOAuthProvider::LINKEDIN,
);
