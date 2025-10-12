<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\GoogleOAuthConfig;
use Tempest\Auth\OAuth\SupportedOAuthProvider;

return new GoogleOAuthConfig(
    clientId: 'OAUTH_GOOGLE_CLIENT_ID',
    clientSecret: 'OAUTH_GOOGLE_CLIENT_SECRET',
    redirectTo: '{REDIRECT_TO}',
    tag: SupportedOAuthProvider::GOOGLE,
);
