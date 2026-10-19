## Unreleased

### Maintenance mode before the autoloader

Maintenance mode can now answer requests before `vendor/autoload.php` is loaded, so requests during `composer install` get a 503 instead of a 500. New applications include this check, but existing ones need to add it to `public/index.php`. See [covering the whole deploy](https://tempestphp.com/main/features/maintenance-mode#covering-the-whole-deploy) for how.

## 2.0

Read the 2.0 upgrade guide here: [https://tempestphp.com/blog/tempest-2](https://tempestphp.com/blog/tempest-2).