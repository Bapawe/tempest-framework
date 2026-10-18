<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Http\Maintenance\Fixtures;

use Tempest\Router\Get;

final readonly class MaintenanceModeController
{
    #[Get('/maintenance-mode/page')]
    public function page(): string
    {
        return 'Handled normally';
    }
}
