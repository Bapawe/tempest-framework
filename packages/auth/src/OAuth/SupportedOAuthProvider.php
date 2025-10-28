<?php

namespace Tempest\Auth\OAuth;

use Tempest\Auth\OAuth\Config\AppleOAuthConfig;
use Tempest\Auth\OAuth\Config\DiscordOAuthConfig;
use Tempest\Auth\OAuth\Config\FacebookOAuthConfig;
use Tempest\Auth\OAuth\Config\GenericOAuthConfig;
use Tempest\Auth\OAuth\Config\GitHubOAuthConfig;
use Tempest\Auth\OAuth\Config\GoogleOAuthConfig;
use Tempest\Auth\OAuth\Config\InstagramOAuthConfig;
use Tempest\Auth\OAuth\Config\LinkedInOAuthConfig;
use Tempest\Auth\OAuth\Config\MicrosoftOAuthConfig;
use Tempest\Auth\OAuth\Config\SlackOAuthConfig;

enum SupportedOAuthProvider: string
{
    case APPLE = 'apple';
    case DISCORD = 'discord';
    case FACEBOOK = 'facebook';
    case GENERIC = 'generic';
    case GITHUB = 'github';
    case GOOGLE = 'google';
    case INSTAGRAM = 'instagram';
    case LINKEDIN = 'linkedin';
    case MICROSOFT = 'microsoft';
    case SLACK = 'slack';

    public function configStub(): string
    {
        return match ($this) {
            self::APPLE => __DIR__ . '/../Installer/oath/apple.config.stub.php',
            self::DISCORD => __DIR__ . '/../Installer/oath/discord.config.stub.php',
            self::FACEBOOK => __DIR__ . '/../Installer/oath/facebook.config.stub.php',
            self::GENERIC => __DIR__ . '/../Installer/oath/generic.config.stub.php',
            self::GITHUB => __DIR__ . '/../Installer/oath/github.config.stub.php',
            self::GOOGLE => __DIR__ . '/../Installer/oath/google.config.stub.php',
            self::INSTAGRAM => __DIR__ . '/../Installer/oath/instagram.config.stub.php',
            self::LINKEDIN => __DIR__ . '/../Installer/oath/linkedin.config.stub.php',
            self::MICROSOFT => __DIR__ . '/../Installer/oath/microsoft.config.stub.php',
            self::SLACK => __DIR__ . '/../Installer/oath/slack.config.stub.php',
        };
    }

    public function composerPackage(): string
    {
        return match ($this) {
            self::APPLE => AppleOAuthConfig::composerPackage(),
            self::DISCORD => DiscordOAuthConfig::composerPackage(),
            self::FACEBOOK => FacebookOAuthConfig::composerPackage(),
            self::GENERIC => GenericOAuthConfig::composerPackage(),
            self::GITHUB => GitHubOAuthConfig::composerPackage(),
            self::GOOGLE => GoogleOauthConfig::composerPackage(),
            self::INSTAGRAM => InstagramOAuthConfig::composerPackage(),
            self::LINKEDIN => LinkedInOAuthConfig::composerPackage(),
            self::MICROSOFT => MicrosoftOAuthConfig::composerPackage(),
            self::SLACK => SlackOAuthConfig::composerPackage(),
        };
    }
}
