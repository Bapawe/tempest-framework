<?php

declare(strict_types=1);

use Tempest\Auth\Installer\oath\GitHubOAuthController;
use Tempest\Auth\OAuth\Config\GitHubOAuthConfig;

return new GitHubOAuthConfig(
    clientId: 'OAUTH_GITHUB_CLIENT_ID',
    clientSecret: 'OAUTH_GITHUB_CLIENT_SECRET',
    redirectTo: [GitHubOAuthController::class, 'callback'],
    tag: GitHubOAuthController::class,
);
