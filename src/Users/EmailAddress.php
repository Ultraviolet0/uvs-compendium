<?php

declare(strict_types=1);

namespace Uvs\Users;

final class EmailAddress
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public static function isValid(string $email): bool
    {
        $email = trim($email);
        return $email !== '' && strlen($email) <= 254
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && !preg_match('/[\r\n]/', $email);
    }
}
