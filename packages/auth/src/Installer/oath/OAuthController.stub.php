<?php

use Tempest\Auth\Authentication\Authenticatable;
use Tempest\Auth\OAuth\OAuthClient;
use Tempest\Auth\OAuth\OAuthUser;
use Tempest\Http\Request;
use Tempest\Http\Responses\Redirect;
use Tempest\Router\Get;

use function Tempest\Database\query;

final readonly class OAuthController
{
    public function __construct(
        private OAuthClient $oauth,
    ) {}

    #[Get('/auth/{ROUTE}')]
    public function redirect(): Redirect
    {
        return $this->oauth->createRedirect(scopes: ['identify']);
    }

    #[Get('/auth/{ROUTE}/callback')]
    public function handleCallback(Request $request): Redirect
    {
        $this->oauth->authenticate(
            request: $request,
            map: fn (OAuthUser $user): Authenticatable => query(User::class)->updateOrCreate([
                '{COLUMN_PREFIX}_id' => $user->id,
            ], [
                '{COLUMN_PREFIX}_id' => $user->id,
                'username' => $user->nickname,
                'email' => $user->email,
            ])
        );

        return new Redirect('/');
    }
}
