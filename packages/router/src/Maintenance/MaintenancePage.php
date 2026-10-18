<?php

declare(strict_types=1);

namespace Tempest\Router\Maintenance;

/**
 * The 503 bodies rendered when maintenance mode was enabled, so they can be served without booting it.
 */
final readonly class MaintenancePage
{
    public function __construct(
        /** The body sent to clients that accept HTML. */
        public string $html,

        /** The body sent to clients that accept JSON. */
        public string $json,
    ) {}

    /**
     * A plain page that needs no rendering, used when the application's maintenance page fails to render.
     */
    public static function builtIn(): self
    {
        return new self(
            html: '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>503 Service Unavailable</title></head><body><h1>503 Service Unavailable</h1><p>The service is temporarily unavailable, please try again later.</p></body></html>',
            json: '{"message":"Service Unavailable"}',
        );
    }
}
