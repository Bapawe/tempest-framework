<?php

/*
 * Copied by `maintenance:enable` as `check.php` into the `maintenance` folder of the internal storage directory, next
 * to the files it reads, and included by `public/index.php` before the autoloader, so requests get a 503 even while
 * `vendor/` or the application can't be loaded. It must stay plain PHP with no dependencies. When the state can't be
 * read, it lets the request through.
 */

(static function (): void {
    $json = @file_get_contents(__DIR__ . '/state.json');

    if ($json === false) {
        return;
    }

    $state = json_decode($json, associative: true);

    // The same validation as `MaintenanceMode::current()`
    if (! is_array($state) || ! is_int($state['enabled_since'] ?? null)) {
        return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';
    $regex = $state['excluded_paths_regex'] ?? null;

    // The same matching as `ExcludedPaths::matches()`, through the regex it compiled
    if (is_string($regex) && @preg_match($regex, $path) === 1) {
        return;
    }

    http_response_code(503);

    if (is_int($state['retry'] ?? null)) {
        header('Retry-After: ' . $state['retry']);
    }

    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        readfile(__DIR__ . '/page.json');
    } else {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/page.html');
    }

    exit();
})();
