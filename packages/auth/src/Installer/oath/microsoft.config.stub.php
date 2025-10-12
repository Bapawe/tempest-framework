<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\MicrosoftOAuthConfig;

return new MicrosoftOAuthConfig(
    clientId: 'OAUTH_MICROSOFT_CLIENT_ID',
    clientSecret: 'OAUTH_MICROSOFT_CLIENT_SECRET',
    redirectTo: '{REDIRECT_TO}',
);
