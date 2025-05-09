<?php

namespace Tempest\Router\Security;

enum CsrfFieldType: string
{
    case PARAMETER = '_token';
    case HEADER = 'X-CSRF-Token';
}
