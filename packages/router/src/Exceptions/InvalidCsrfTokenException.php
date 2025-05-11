<?php

declare(strict_types=1);

namespace Tempest\Router\Exceptions;

use LogicException;

final class InvalidCsrfTokenException extends LogicException implements CsrfException
{
    public function __construct()
    {
        // TODO: improve exception message to help dev
        parent::__construct('Invalid CSRF token');
    }
}
