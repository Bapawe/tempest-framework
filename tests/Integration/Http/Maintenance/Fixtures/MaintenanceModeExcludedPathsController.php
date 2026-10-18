<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance\Fixtures;

use Tempest\Router\Get;

final readonly class MaintenanceModeExcludedPathsController
{
    #[Get('/health')]
    public function health(): string
    {
        return 'Healthy';
    }

    #[Get('/webhooks')]
    public function webhooks(): string
    {
        return 'Webhooks index';
    }

    #[Get('/webhooks/stripe')]
    public function stripe(): string
    {
        return 'Stripe webhook';
    }

    #[Get('/webhooks/stripe/events')]
    public function stripeEvents(): string
    {
        return 'Stripe events webhook';
    }
}
