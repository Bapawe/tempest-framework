<?php

declare(strict_types=1);

namespace Tempest\Router\Exceptions;

use Exception;

final class CsrfTokenNotFoundException extends Exception implements CsrfException
{
    public function __construct()
    {
        parent::__construct('CSRF token not found');
    }
}
