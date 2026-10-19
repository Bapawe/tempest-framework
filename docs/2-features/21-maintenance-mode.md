---
title: Maintenance mode
description: "Enable maintenance mode while you deploy, migrate or repair your application, so that visitors get a `503 Service Unavailable` instead of a broken page."
---

## Overview

While an application is being deployed, migrated or repaired, visitors can hit half-updated code or a database in the middle of a migration. While maintenance mode is enabled, the application answers every HTTP request with a `503 Service Unavailable` instead of handling it.

The 503 goes through Tempest's normal [exception rendering](./14-exception-handling.md), so clients that accept HTML get an error page and clients that accept JSON get a JSON error response.

Maintenance mode covers HTTP only. Console commands and [scheduled tasks](./11-scheduling.md) keep running, so you can still run migrations while it is enabled.

## Enabling maintenance mode

The `{txt}maintenance:enable` command enables maintenance mode, and `{txt}maintenance:disable` disables it. Both take effect immediately, including on workers that are already running.

```console
<dim>./tempest maintenance:enable</dim>
<dim>./tempest maintenance:disable</dim>
```

The `--retry` option tells clients how many seconds you expect maintenance mode to last. It adds a `{txt}{:hl-property:Retry-After:}` header to every 503 response, which crawlers and uptime monitors use to wait before trying again:

```console
<dim>./tempest maintenance:enable --retry=600</dim>
```

In the production and staging environments, `{txt}maintenance:enable` asks for confirmation. In scripts, pass `--force` to skip it:

```sh
./tempest maintenance:enable --force --retry=300

{:hl-comment:# Install dependencies, run migrations, build assets, …:}

./tempest maintenance:disable
```

:::warning
Don't use `--no-interaction` instead of `--force`. Without anyone to answer, the confirmation falls back to "no", and maintenance mode stays disabled.
:::

The `{txt}maintenance:status` command shows whether maintenance mode is enabled, along with its settings. It exits with `0` when maintenance mode is disabled and `1` when it is enabled, so scripts can branch on it.

## Excluded paths

Some endpoints may need to keep working while maintenance mode is enabled, such as health checks. You may list them as excluded paths in a `maintenance.config.php` file:

```php app/maintenance.config.php
use Tempest\Router\Maintenance\MaintenanceConfig;

return new MaintenanceConfig(
    excludedPaths: [
        '/health',
        '/health/*',
    ],
);
```

An excluded path is either an exact path, or a prefix ending in `*` that matches anything below it. Patterns are matched against the request path, without the query string.

Requests to excluded paths are handled normally, which means they run against the code and database that may be in the middle of being changed.

## Customizing the maintenance page

The 503 is rendered from a {b`Tempest\Router\Maintenance\MaintenanceModeWasActive`} exception. To use your own maintenance page, write a [custom exception renderer](./14-exception-handling.md#customizing-exception-rendering) that matches it:

```php app/MaintenancePageRenderer.php
use Tempest\Http\ContentType;
use Tempest\Http\GenericResponse;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Status;
use Tempest\Router\Exceptions\ExceptionRenderer;
use Tempest\Router\Maintenance\MaintenanceModeWasActive;
use Throwable;

use function Tempest\View\view;

final class MaintenancePageRenderer implements ExceptionRenderer
{
    public function canRender(Throwable $throwable, Request $request): bool
    {
        return $throwable instanceof MaintenanceModeWasActive
            && $request->accepts(ContentType::HTML);
    }

    public function render(Throwable $throwable): Response
    {
        return new GenericResponse(
            status: Status::SERVICE_UNAVAILABLE,
            body: view('./maintenance.view.php'),
        );
    }
}
```

The `{txt}{:hl-property:Retry-After:}` header is added for you. Requests your renderer doesn't handle still get Tempest's default 503 response.

:::info
`{txt}maintenance:enable` saves the excluded paths and renders the maintenance page at the moment it runs. If you change either while maintenance mode is enabled, run `{txt}maintenance:enable` again to apply the changes.
:::

## Covering the whole deploy

Maintenance mode is normally checked once the application has booted. While `{txt}composer install` is running, though, the `vendor` directory is only half updated, so booting can fail before that check is reached and visitors get a 500 instead of a 503.

To cover that window, `public/index.php` checks for maintenance mode before it loads the autoloader:

```php public/index.php
use Tempest\Router\HttpApplication;

if (is_file($maintenance = __DIR__ . '/../.tempest/maintenance/check.php')) {
    require $maintenance;
}

require_once __DIR__ . '/../vendor/autoload.php';

HttpApplication::boot(__DIR__ . '/../')->run();
```

New applications include this check. For an existing application, add the `if` block above the line that requires `vendor/autoload.php`. If you haven't changed `public/index.php`, you may instead run `{txt}./tempest install framework`, which asks for each file it publishes, and only let it override `public/index.php`. If you pass a custom `internalStorage` directory to `HttpApplication::boot()`, replace `.tempest` in the path with that directory.

## Limitations

- **Static pages stay reachable.** [Static pages](./13-static-pages.md) are served directly by your web server without booting Tempest, so maintenance mode doesn't apply to them.
- **Maintenance mode only applies to the current server.** Its state is stored in Tempest's internal storage directory (`.tempest` by default). If your application runs on several servers, enable and disable it on each of them.
