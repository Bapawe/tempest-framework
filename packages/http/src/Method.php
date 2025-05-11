<?php

declare(strict_types=1);

namespace Tempest\Http;

enum Method: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case CONNECT = 'CONNECT';
    case OPTIONS = 'OPTIONS';
    case TRACE = 'TRACE';
    case PATCH = 'PATCH';

    public function modifiesState(): bool
    {
        return match ($this) {
            self::PATCH, self::PUT, self::POST, self::DELETE => true,
            default => false,
        };
    }
}
