<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\SlackOAuthConfig;

return new SlackOAuthConfig(
    clientId: 'OAUTH_SLACK_CLIENT_ID',
    clientSecret: 'OAUTH_SLACK_CLIENT_SECRET',
    redirectTo: [\Tempest\Auth\Installer\oauth\OAuthControllerStub::class, 'callback'],
    tag: \Tempest\Auth\OAuth\SupportedOAuthProvider::SLACK,
);
