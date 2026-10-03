<?php

declare(strict_types=1);

namespace Uvs\Http;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '', public readonly ?string $detail = null)
    {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(404, 'Page not found');
    }

    public static function forbidden(string $detail = 'You do not have permission to view this page.'): self
    {
        return new self(403, 'Access denied', $detail);
    }
}
