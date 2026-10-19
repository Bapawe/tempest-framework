<?php

namespace Tempest\Router\Exceptions;

use Tempest\Container\Container;
use Tempest\Core\ExceptionHandler;
use Tempest\Core\Exceptions\ExceptionProcessor;
use Tempest\Core\Kernel;
use Tempest\Http\GenericResponse;
use Tempest\Http\Header;
use Tempest\Http\HttpRequestFailed;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Status;
use Tempest\Router\Maintenance\MaintenanceModeWasEnabled;
use Tempest\Router\Maintenance\MaintenancePage;
use Tempest\Router\ResponseSender;
use Tempest\Router\RouteConfig;
use Tempest\Support\Arr;
use Throwable;

final readonly class HttpExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private ResponseSender $responseSender,
        private Kernel $kernel,
        private Container $container,
        private ExceptionProcessor $exceptionProcessor,
        private RouteConfig $routeConfig,
    ) {}

    public function handle(Throwable $throwable): void
    {
        $request = $this->container->get(Request::class);

        try {
            $this->exceptionProcessor->process($throwable);
            $this->responseSender->send($this->renderResponse($request, $throwable));
        } finally {
            $this->kernel->shutdown();
        }
    }

    public function renderResponse(Request $request, Throwable $throwable): Response
    {
        ksort($this->routeConfig->exceptionRenderers);

        foreach (Arr\flatten($this->routeConfig->exceptionRenderers) as $rendererClass) {
            /** @var ExceptionRenderer $renderer */
            $renderer = $this->container->get($rendererClass);

            if ($renderer->canRender($throwable, $request)) {
                return $this->applyHeaders($renderer->render($throwable), $throwable);
            }
        }

        // Maintenance mode always answers with a 503, so that clients learn when to retry instead of recording a failure
        if ($throwable instanceof MaintenanceModeWasEnabled) {
            return $this->applyHeaders(
                new GenericResponse(
                    status: Status::SERVICE_UNAVAILABLE,
                    body: MaintenancePage::builtIn()->html,
                    headers: ['Content-Type' => 'text/html; charset=utf-8'],
                ),
                $throwable,
            );
        }

        return new GenericResponse(status: Status::NOT_ACCEPTABLE);
    }

    /**
     * Applies the headers declared by a failure, which renderers would otherwise discard by building a response from scratch.
     */
    private function applyHeaders(Response $response, Throwable $throwable): Response
    {
        $headers = match (true) {
            $throwable instanceof HttpRequestFailed => $throwable->headers,
            $throwable instanceof MaintenanceModeWasEnabled && $throwable->retry !== null => ['Retry-After' => (string) $throwable->retry],
            default => [],
        };

        foreach ($headers as $name => $values) {
            // Headers keep the casing they were added with, so we look up the existing one to avoid a duplicate
            $existing = $response->getHeader($name);

            $response->removeHeader($existing instanceof Header ? $existing->name : $name);

            foreach (is_array($values) ? $values : [$values] as $value) {
                $response->addHeader($name, $value);
            }
        }

        return $response;
    }
}
