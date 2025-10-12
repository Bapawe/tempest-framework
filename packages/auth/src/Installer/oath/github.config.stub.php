<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\GitHubOAuthConfig;
use Tempest\Auth\OAuth\SupportedOAuthProvider;

return new GitHubOAuthConfig(
    clientId: 'OAUTH_GITHUB_CLIENT_ID',
    clientSecret: 'OAUTH_GITHUB_CLIENT_SECRET',
    redirectTo: '{REDIRECT_TO}',
    tag: SupportedOAuthProvider::GITHUB,
);
