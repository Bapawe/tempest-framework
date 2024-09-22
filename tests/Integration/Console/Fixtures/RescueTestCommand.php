<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Console\Fixtures;

use Tempest\Console\ConsoleCommand;

final readonly class RescueTestCommand
{
    #[ConsoleCommand('rescue-test:rescue', aliases: ['rtr'])]
    public function __invoke(): void
    {
        // TODO: Implement __invoke() method.
    }
}
