<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\GenericOAuthConfig;

return new GenericOAuthConfig(
    clientId: 'OAUTH_GENERIC_CLIENT_ID',
    clientSecret: 'OAUTH_GENERIC_CLIENT_SECRET',
    redirectTo: '',
    urlAuthorize: 'OAUTH_GENERIC_URL_AUTHORIZE',
    urlAccessToken: 'OAUTH_GENERIC_URL_ACCESS_TOKEN',
    urlResourceOwnerDetails: 'OAUTH_GENERIC_URL_RESOURCE_OWNER_DETAILS',
);
