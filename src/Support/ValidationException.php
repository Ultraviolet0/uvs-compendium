<?php

declare(strict_types=1);

namespace Uvs\Support;

use RuntimeException;

/**
 * Field-keyed validation errors safe to show to the person who submitted the form.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    public function first(): string
    {
        return (string) (array_values($this->errors)[0] ?? 'Please check the form.');
    }
}
