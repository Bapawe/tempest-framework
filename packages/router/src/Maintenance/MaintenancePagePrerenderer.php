<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use JsonSerializable;
use Tempest\Container\Container;
use Tempest\Http\ContentType;
use Tempest\Http\GenericRequest;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Router\Exceptions\HttpExceptionHandler;
use Tempest\Support\Json;
use Tempest\View\View;
use Tempest\View\ViewRenderer;

/**
 * Renders the maintenance page through the application's exception renderers, from a synthetic `GET /` request.
 */
final readonly class MaintenancePagePrerenderer
{
    public function __construct(
        private HttpExceptionHandler $exceptionHandler,
        private ViewRenderer $viewRenderer,
        private Container $container,
    ) {}

    public function render(MaintenanceModeWasEnabled $exception): MaintenancePage
    {
        return new MaintenancePage(
            html: $this->renderBody($exception, ContentType::HTML),
            json: $this->renderBody($exception, ContentType::JSON),
        );
    }

    private function renderBody(MaintenanceModeWasEnabled $exception, ContentType $accept): string
    {
        $request = new GenericRequest(method: Method::GET, uri: '/', headers: ['Accept' => $accept->value]);

        return $this->withRequest($request, function () use ($request, $exception): string {
            $body = $this->exceptionHandler->renderResponse($request, $exception)->body;

            return match (true) {
                $body instanceof View => $this->viewRenderer->render($body),
                is_array($body) || $body instanceof JsonSerializable => Json\encode($body),
                default => (string) $body,
            };
        });
    }

    /**
     * Binds the synthetic request while rendering, since renderers and views inject the current request and the console has none.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withRequest(Request $request, callable $callback): mixed
    {
        $previous = $this->container->has(Request::class) ? $this->container->get(Request::class) : null;

        $this->container->singleton(Request::class, $request);

        try {
            return $callback();
        } finally {
            if ($previous instanceof Request) {
                $this->container->singleton(Request::class, $previous);
            } else {
                $this->container->unregister(Request::class);
            }
        }
    }
}
