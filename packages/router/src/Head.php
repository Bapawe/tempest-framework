<?php

declare(strict_types=1);

namespace Tempest\Router;

use Attribute;
use Tempest\Http\Method;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class Head implements Route
{
    use IsRoute;

    public Method $method {
        get => Method::HEAD;
    }
}
