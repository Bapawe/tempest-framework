<?php

namespace Tempest\Auth\Installer\oath;

use Tempest\Auth\Authentication\Authenticatable;
use Tempest\Auth\Installer\UserModel;
use Tempest\Auth\OAuth\OAuthClient;
use Tempest\Auth\OAuth\OAuthUser;
use Tempest\Container\Tag;
use Tempest\Http\Request;
use Tempest\Http\Responses\Redirect;
use Tempest\Router\Get;

use function Tempest\Database\query;

final readonly class AppleOAuthController
{
    public function __construct(
        #[Tag(self::class)]
        private OAuthClient $oauth,
    ) {
    }

    #[Get('/auth/github')]
    public function redirect(): Redirect
    {
        return $this->oauth->createRedirect();
    }

    #[Get('/auth/github/callback')]
    public function callback(Request $request): Redirect
    {
        $this->oauth->authenticate(
            request: $request,
            map: fn(OAuthUser $user): Authenticatable => query(UserModel::class)->updateOrCreate([
                'github_id' => $user->id,
            ], [
                'github_id' => $user->id,
                'username' => $user->nickname,
                'email' => $user->email,
            ])
        );

        return new Redirect('/');
    }
}
