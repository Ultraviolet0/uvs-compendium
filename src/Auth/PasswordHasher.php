<?php

declare(strict_types=1);

namespace Uvs\Auth;

/**
 * Password hashing through PHP's password API. Argon2id is preferred when the
 * PHP build provides it; otherwise bcrypt (PASSWORD_DEFAULT) is used and the
 * password policy enforces bcrypt's 72-byte input limit.
 */
final class PasswordHasher
{
    public static function algorithm(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function usesBcrypt(): bool
    {
        return !defined('PASSWORD_ARGON2ID');
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    /**
     * Spends comparable time when no account matched, reducing a timing signal
     * that would otherwise reveal whether a login identifier exists.
     */
    public static function burn(string $password): void
    {
        static $dummy = null;
        $dummy ??= self::hash('uvs-compendium-timing-equaliser');
        password_verify($password, $dummy);
    }
}
