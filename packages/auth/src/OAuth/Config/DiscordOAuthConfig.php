<?php

declare(strict_types=1);

namespace Tempest\Auth\OAuth\Config;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Tempest\Auth\OAuth\OAuthConfig;
use Tempest\Auth\OAuth\OAuthUser;
use Tempest\Mapper\ObjectFactory;
use UnitEnum;
use Wohali\OAuth2\Client\Provider\Discord;

final class DiscordOAuthConfig implements OAuthConfig
{
    public string $provider = Discord::class;

    public function __construct(
        /**
         * The client ID for the Discord OAuth application.
         */
        public string $clientId,

        /**
         * The client secret for the Discord OAuth application.
         */
        public string $clientSecret,

        /**
         * The controller action to redirect to after the user authorizes the application.
         */
        public string|array $redirectTo,

        /**
         * The scopes to request from Discord.
         *
         * @var string[]
         */
        public array $scopes = ['identify', 'email'],

        /**
         * Identifier for this OAuth configuration.
         */
        public null|string|UnitEnum $tag = null,
    ) {}

    public function createProvider(): AbstractProvider
    {
        return new Discord([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
        ]);
    }

    public function mapUser(ObjectFactory $factory, ResourceOwnerInterface $resourceOwner): OAuthUser
    {
        return $factory
            ->withData([
                'provider' => 'discord',
                'raw' => $data = $resourceOwner->toArray(),
                ...$data,
            ])
            ->to(OAuthUser::class);
    }

    public static function configStub(): string
    {
        return __DIR__ . '/../../Installer/oath/discord.config.stub.php';
    }

    public static function controllerStub(): string
    {
        return __DIR__ . '/../../Installer/oath/DiscordOAuthController.stub.php';
    }

    public static function composerPackage(): string
    {
        return 'wohali/oauth2-discord-new';
    }
}
