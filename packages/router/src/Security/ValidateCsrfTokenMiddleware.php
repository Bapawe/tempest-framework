<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use Tempest\Core\AppConfig;
use Tempest\Core\Priority;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\Forbidden;
use Tempest\Router\Exceptions\CsrfException;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;

#[Priority(Priority::FRAMEWORK)]
final readonly class ValidateCsrfTokenMiddleware implements HttpMiddleware
{
    public function __construct(
        private CsrfValidator $csrfValidator,
        private AppConfig $appConfig,
    ) {}

    /**
     * @throws CsrfException
     */
    public function __invoke(Request $request, HttpMiddlewareCallable $next): Response
    {
        if ($this->csrfValidator->shouldValidate()) {
            try {
                $this->csrfValidator->validate($request);
            } catch (CsrfException $csrfValidationException) {
                if ($this->appConfig->environment->isLocal()) {
                    throw $csrfValidationException;
                }

                return new Forbidden();
            }
        }

        return $next($request);
    }
}
