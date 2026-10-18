<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

use JsonSerializable;
use Tempest\Http\ContentType;
use Tempest\Http\GenericRequest;
use Tempest\Http\Method;
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
    ) {}

    public function render(MaintenanceModeWasActive $exception): MaintenancePage
    {
        return new MaintenancePage(
            html: $this->renderBody($exception, ContentType::HTML),
            json: $this->renderBody($exception, ContentType::JSON),
        );
    }

    private function renderBody(MaintenanceModeWasActive $exception, ContentType $accept): string
    {
        $request = new GenericRequest(method: Method::GET, uri: '/', headers: ['Accept' => $accept->value]);
        $body = $this->exceptionHandler->renderResponse($request, $exception)->body;

        return match (true) {
            $body instanceof View => $this->viewRenderer->render($body),
            is_array($body) || $body instanceof JsonSerializable => Json\encode($body),
            default => (string) $body,
        };
    }
}
