<?php

declare(strict_types=1);

namespace App\Controllers;

use Tempest\Http\HttpMiddleware;
use Tempest\Http\Request;
use Tempest\Http\Response;

final readonly class TestMiddleware implements HttpMiddleware
{
    public function __construct(
        private MiddlewareDependency $middlewareDependency,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        /** @var \Tempest\Http\Response $response */
        $response = $next($request);

        $response->header('middleware', $this->middlewareDependency->value);

        return $response;
    }
}
