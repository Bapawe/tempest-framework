## Unreleased

### Maintenance mode before the autoloader

Maintenance mode can now answer requests before `vendor/autoload.php` is loaded, so requests during `composer install` get a 503 instead of a 500. New applications include this check, but existing ones need to add it to `public/index.php`, above the line that requires `vendor/autoload.php`:

```php
if (is_file($maintenance = __DIR__ . '/../.tempest/maintenance/check.php')) {
    require $maintenance;
}
```

Alternatively, run `./tempest install framework` and let it override `public/index.php`. This replaces the whole file, so only do this if you haven't changed it.

If you pass a custom `internalStorage` directory to `HttpApplication::boot()`, change `.tempest` in the path to that directory. See [maintenance mode](https://tempestphp.com/main/features/maintenance-mode#covering-the-whole-deploy).

### `HandleRouteExceptionMiddleware` priority

`HandleRouteExceptionMiddleware` moved from `Priority::FRAMEWORK - 30` to `Priority::FRAMEWORK - 31`, so that it wraps the new `CheckMaintenanceModeMiddleware` at `Priority::FRAMEWORK - 30`. If you placed your own middleware relative to `Priority::FRAMEWORK - 30`, check that it still runs where you expect.

## 2.0

Read the 2.0 upgrade guide here: [https://tempestphp.com/blog/tempest-2](https://tempestphp.com/blog/tempest-2).