<?php

declare(strict_types=1);

use Tempest\Auth\OAuth\Config\DiscordOAuthConfig;
use Tempest\Auth\OAuth\SupportedOAuthProvider;

return new DiscordOAuthConfig(
    clientId: 'OAUTH_DISCORD_CLIENT_ID',
    clientSecret: 'OAUTH_DISCORD_CLIENT_SECRET',
    redirectTo: '{REDIRECT_TO}',
    tag: SupportedOAuthProvider::DISCORD,
);
