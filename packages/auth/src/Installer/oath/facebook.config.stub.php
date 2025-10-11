<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\FacebookOAuthConfig;

return new FacebookOAuthConfig(
    clientId: 'OAUTH_FACEBOOK_CLIENT_ID',
    clientSecret: 'OAUTH_FACEBOOK_CLIENT_SECRET',
    redirectTo: '',
);
