<?php

declare(strict_types=1);

namespace Tempest\Router\Exceptions;

use LogicException;

final class CsrfTokenNotFoundException extends LogicException implements CsrfException
{
    public function __construct()
    {
        // TODO: improve exception message to help dev
        parent::__construct('CSRF token not found');
    }
}
