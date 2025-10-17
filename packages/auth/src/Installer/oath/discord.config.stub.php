<?php

declare(strict_types=1);

use Tempest\Auth\Installer\oath\DiscordOAuthController;
use Tempest\Auth\OAuth\Config\DiscordOAuthConfig;

return new DiscordOAuthConfig(
    clientId: 'OAUTH_DISCORD_CLIENT_ID',
    clientSecret: 'OAUTH_DISCORD_CLIENT_SECRET',
    redirectTo: [DiscordOAuthController::class, 'callback'],
    tag: DiscordOAuthController::class,
);
