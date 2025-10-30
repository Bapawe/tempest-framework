<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\GitHubOAuthConfig;

return new GitHubOAuthConfig(
    clientId: 'OAUTH_GITHUB_CLIENT_ID',
    clientSecret: 'OAUTH_GITHUB_CLIENT_SECRET',
    redirectTo: [\Tempest\Auth\Installer\oath\OAuthControllerStub::class, 'callback'],
    tag: \Tempest\Auth\OAuth\SupportedOAuthProvider::GITHUB,
);
