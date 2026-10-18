---
title: Maintenance mode
description: "Enable maintenance mode while you deploy, migrate or repair your application, so that visitors get a `503 Service Unavailable` instead of a broken page."
---

## Overview

While an application is being deployed, migrated or repaired, visitors can hit half-updated code or a database in the middle of a migration. Maintenance mode prevents this: while it is enabled, the application answers every HTTP request with a `503 Service Unavailable` instead of handling it.

The 503 goes through Tempest's normal [exception rendering](./14-exception-handling.md). Clients that accept HTML get an error page, and clients that accept JSON get a JSON error response. Even URLs that don't match any route get a 503 rather than a 404. In the local environment, you see the same page your visitors would, not the development error page.

Maintenance mode covers HTTP only. Console commands and [scheduled tasks](./11-scheduling.md) keep running while maintenance mode is enabled, so you can still run migrations, and disable maintenance mode again.

## Enabling maintenance mode

The `{txt}maintenance:enable` command enables maintenance mode, and `{txt}maintenance:disable` disables it:

```console
<dim>./tempest maintenance:enable</dim>
<dim>./tempest maintenance:disable</dim>
```

Both commands can safely be run more than once. Running `{txt}maintenance:enable` while maintenance mode is already enabled updates its settings but keeps the original time it was enabled, and prints `Maintenance mode was already enabled, its settings are updated.` Running `{txt}maintenance:disable` while maintenance mode is not enabled prints `Maintenance mode was not enabled.` and does nothing else.

The application checks maintenance mode on every request, so both commands take effect immediately, including on workers that are already running.

### Telling clients when to try again

You may pass the `--retry` option to tell clients how many seconds you expect maintenance mode to stay enabled:

```console
<dim>./tempest maintenance:enable --retry=600</dim>
```

Every 503 response will then carry a `{txt}{:hl-property:Retry-After:}: 600` header, which crawlers and uptime monitors use to wait before trying again, instead of recording the 503 responses as failures.

The retry value must be a whole number of seconds, at least `1`. Lower values such as `--retry=0` are rejected with an error, the command exits with the `INVALID` exit code, and maintenance mode is left as it was.

Without `--retry`, the `{txt}{:hl-property:Retry-After:}` header is left out entirely. There is no default value. Running `{txt}maintenance:enable` again while maintenance mode is enabled replaces the retry value with the new one, or clears it when `--retry` is omitted.

### Confirmation in production

In the production and staging environments, `{txt}maintenance:enable` asks for confirmation before enabling maintenance mode. Declining leaves maintenance mode disabled, and the command exits with the `CANCELLED` exit code. In other environments, such as local, it doesn't ask.

`{txt}maintenance:disable` and `{txt}maintenance:status` never ask for confirmation, so that disabling maintenance mode is never slowed down.

## Checking the status

The `{txt}maintenance:status` command tells you whether maintenance mode is enabled. When it is disabled, it prints the following:

```console
<dim>./tempest maintenance:status</dim>

Maintenance mode is disabled.
```

When maintenance mode is enabled, it prints since when, and the retry value. Without a retry value, the second line says that 503 responses carry no `Retry-After` header.

```console
<dim>./tempest maintenance:status</dim>

Maintenance mode has been enabled since 2025-03-14 09:26:53 (UTC).
Clients are told to retry after 600 seconds.
```

The command exits with `0` (`SUCCESS`) when maintenance mode is disabled and `1` (`ERROR`) when it is enabled. The non-zero exit code means "maintenance mode is enabled", not that the command failed, which is why the output above is an info message rather than a warning. This allows scripts to branch on the state without parsing the output:

```sh
{:hl-keyword:if:} ./tempest maintenance:status; {:hl-keyword:then:}
    {:hl-keyword:echo:} "Maintenance mode is disabled"
{:hl-keyword:else:}
    {:hl-keyword:echo:} "Maintenance mode is enabled"
{:hl-keyword:fi:}
```

## Excluded paths

Some endpoints must keep working while maintenance mode is enabled, such as health checks or payment webhooks. You may list these as excluded paths by creating a `maintenance.config.php` file. Requests to an excluded path are handled normally while maintenance mode is enabled. No paths are excluded by default.

```php app/maintenance.config.php
use Tempest\Router\Maintenance\MaintenanceConfig;

return new MaintenanceConfig(
    excludedPaths: [
        '/health',
        '/webhooks/*',
    ],
);
```

An excluded path is either an exact path or a prefix followed by a trailing `*`. They are matched as follows:

- Patterns are compared against the request path only, so the query string is ignored: `/health?probe=1` matches `/health`.
- An exact path matches only the identical path, including a trailing slash: `/health` doesn't match `/health/`.
- A pattern ending in `*` matches any path that starts with the text before the `*`, followed by at least one more character. For instance, `/webhooks/*` matches `/webhooks/stripe` and `/webhooks/stripe/events`, but not `/webhooks/` or `/webhooks` themselves. If you need those too, list them as separate exact paths.
- The text before the `*` is compared as plain text, not as path segments, so `/api*` matches `/api2` and `/api/users`. End the prefix with a `/`, as in `/api/*`, to only match paths below `/api/`.
- Regular expressions and wildcards in the middle of a pattern are not supported.

Since requests to excluded paths are handled normally, a URL below an excluded prefix that doesn't match any route still returns a 404.

The excluded paths are saved when maintenance mode is enabled. If you change them while maintenance mode is enabled, run `{txt}maintenance:enable` again for the change to take effect.

## Customizing the maintenance page

The 503 is rendered from a {b`Tempest\Router\Maintenance\MaintenanceModeWasActive`} exception, not from an {b`Tempest\Http\HttpRequestFailed`}. A [custom exception renderer](./14-exception-handling.md#customizing-exception-rendering) that checks for `HttpRequestFailed` and a `503` status won't see it. To use your own maintenance page, match `MaintenanceModeWasActive` instead:

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

The `{txt}{:hl-property:Retry-After:}` header is added to the response your renderer returns, so you don't need to set it yourself. Requests that your renderer doesn't handle, such as JSON requests in the example above, still get Tempest's default 503 response.

`{txt}maintenance:enable` renders the maintenance page once, when maintenance mode is enabled, for the [early check](#covering-the-whole-deploy). If you change the page while maintenance mode is enabled, run `{txt}maintenance:enable` again for the change to take effect. If the page can't be rendered, the command prints a warning and uses Tempest's built-in 503 page instead.

## Deploy scripts

In a [deployment script](../5-extra-topics/03-deployments.md), pass the `--force` flag to `{txt}maintenance:enable`, so it doesn't ask for confirmation in production:

```sh
./tempest maintenance:enable --force --retry=300

{:hl-comment:# Install dependencies, run migrations, build assets, …:}

./tempest maintenance:disable
```

:::warning
Don't use `--no-interaction` for this. Without anyone to answer, the confirmation falls back to "no", so `{txt}maintenance:enable --no-interaction` cancels and leaves maintenance mode disabled in production and staging.
:::

## Covering the whole deploy

Maintenance mode is normally checked once the application has booted. While `{txt}git pull` or `{txt}composer install` is running, though, the application code or the `vendor` directory is only half updated, so booting can fail before that check is reached, and visitors get a 500 instead of a 503.

To cover that window, `public/index.php` checks for maintenance mode before it loads the autoloader:

```php public/index.php
use Tempest\Router\HttpApplication;

if (is_file($maintenance = __DIR__ . '/../.tempest/maintenance.php')) {
    require $maintenance;
}

require_once __DIR__ . '/../vendor/autoload.php';

HttpApplication::boot(__DIR__ . '/../')->run();
```

`{txt}maintenance:enable` places `.tempest/maintenance.php` next to the maintenance state, and `{txt}maintenance:disable` removes it. While maintenance mode is enabled, it answers requests with a 503 using only plain PHP, so it works even when the `vendor` directory can't be loaded. It sends the `{txt}{:hl-property:Retry-After:}` header and the HTML or JSON page that `{txt}maintenance:enable` rendered, and lets requests to [excluded paths](#excluded-paths) through to your application. If the maintenance state is missing or can't be read, it lets every request through, so a leftover file never keeps maintenance mode enabled. The rest of Tempest treats such a state the same way: maintenance mode counts as disabled, `{txt}maintenance:status` says so, and `{txt}maintenance:disable` removes the leftover files.

New applications include this check in `public/index.php`. If your application was created before, add the `if` block above the line that requires `vendor/autoload.php`. If you haven't changed `public/index.php`, you may instead run `{txt}tempest install framework` and let it override that file. If you pass a custom `internalStorage` directory to `HttpApplication::boot()`, change `.tempest` in the path to that directory.

Without the check, maintenance mode still works once the application has booted, including in worker mode, but requests during `{txt}composer install` may fail with a 500.

### Release directories

If you deploy each release to its own directory and switch a symlink between them, share the `.tempest` directory between releases, as you would `.env`. Otherwise, the new release starts without the maintenance state, and handles requests as soon as the symlink switches, before your deploy script runs `{txt}maintenance:disable`.

## Limitations

- **Static pages stay reachable.** [Static pages](./13-static-pages.md) generated with `{txt}static:generate` are served directly by your web server without booting Tempest, so maintenance mode doesn't apply to them.
- **Maintenance mode only applies to the current server.** The state is stored as a file in Tempest's internal storage directory (`.tempest` by default). If your application runs on several servers, run `{txt}maintenance:enable` and `{txt}maintenance:disable` on each of them.
