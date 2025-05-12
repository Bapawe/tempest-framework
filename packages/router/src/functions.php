<?php

declare(strict_types=1);

namespace Tempest {
    use Tempest\Reflection\MethodReflector;
    use Tempest\Router\Router;
    use Tempest\Router\Security\CsrfConfig;
    use Tempest\Router\Security\CsrfToken;
    use Tempest\Router\Security\CsrfTokenManager;
    use Tempest\Support\Html\HtmlString;

    use function Tempest\Support\Html\create_tag;

    /**
     * Creates a valid URI to the given controller `$action`.
     */
    function uri(array|string|MethodReflector $action, mixed ...$params): string
    {
        if ($action instanceof MethodReflector) {
            $action = [
                $action->getDeclaringClass()->getName(),
                $action->getName(),
            ];
        }

        $router = get(Router::class);

        return $router->toUri(
            $action,
            ...$params,
        );
    }

    /**
     * Checks whether the given controller action matches the current URI.
     */
    function is_current_uri(array|string|MethodReflector $action, mixed ...$params): bool
    {
        if ($action instanceof MethodReflector) {
            $action = [
                $action->getDeclaringClass()->getName(),
                $action->getName(),
            ];
        }

        $router = get(Router::class);

        return $router->isCurrentUri(
            $action,
            ...$params,
        );
    }

    /**
     * Create a CSRF token form field.
     */
    function csrf_field(?string $id = null, ?string $name = null): HtmlString
    {
        $csrfConfig = get(CsrfConfig::class);

        return create_tag(
            'input',
            [
                'type' => 'hidden',
                'name' => $name ?: $csrfConfig->tokenKey,
                'value' => csrf_token($id),
                'autocomplete' => 'off',
            ],
        );
    }

    /**
     * Get the CSRF token.
     */
    function csrf_token(?string $id = null): CsrfToken
    {
        $csrfTokenManager = get(CsrfTokenManager::class);
        $csrfConfig = get(CsrfConfig::class);

        return $csrfTokenManager->getToken($id ?? $csrfConfig->tokenId);
    }
}
