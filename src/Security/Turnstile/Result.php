<?php

declare(strict_types=1);

namespace Uvs\Security\Turnstile;

final class Result
{
    /**
     * @param list<string> $errors Cloudflare error codes or local reasons; never secrets.
     */
    public function __construct(public readonly bool $success, public readonly array $errors = [])
    {
    }
}
