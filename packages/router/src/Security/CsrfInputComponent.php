<?php

declare(strict_types=1);

namespace Tempest\Router\Security;

use Tempest\View\Elements\ViewComponentElement;
use Tempest\View\ViewComponent;

final readonly class CsrfInputComponent implements ViewComponent
{
    public static function getName(): string
    {
        return 'x-csrf-input';
    }

    public function compile(ViewComponentElement $element): string
    {
        return <<<HTML
            <?= \Tempest\\csrf_field() ?>
        HTML;
    }
}
