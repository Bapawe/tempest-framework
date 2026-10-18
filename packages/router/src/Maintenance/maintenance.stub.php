<?php

/*
 * Copied to the internal storage directory by `maintenance:enable`, and included by `public/index.php` before the
 * autoloader, so requests get a 503 even while `vendor/` or the application can't be loaded. It must stay plain PHP
 * with no dependencies. When the state can't be read, it lets the request through.
 */

(static function (): void {
    $json = @file_get_contents(__DIR__ . '/maintenance.json');

    if ($json === false) {
        return;
    }

    $state = json_decode($json, associative: true);

    if (! is_array($state)) {
        return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';

    // The same matching rules as `CheckMaintenanceModeMiddleware`
    foreach ($state['excluded_paths'] ?? [] as $pattern) {
        if (! str_ends_with($pattern, '*')) {
            if ($path === $pattern) {
                return;
            }

            continue;
        }

        $prefix = substr($pattern, 0, -1);

        if (strlen($path) > strlen($prefix) && str_starts_with($path, $prefix)) {
            return;
        }
    }

    http_response_code(503);

    if (is_int($state['retry'] ?? null)) {
        header('Retry-After: ' . $state['retry']);
    }

    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        readfile(__DIR__ . '/maintenance-body.json');
    } else {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/maintenance.html');
    }

    exit();
})();
