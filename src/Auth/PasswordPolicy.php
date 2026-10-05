<?php

declare(strict_types=1);

namespace Uvs\Auth;

/**
 * Passphrase-friendly password rules: length over composition, with a small
 * deny-list of extremely common choices.
 */
final class PasswordPolicy
{
    private const COMMON = [
        'password', 'password1', 'password12', 'password123', 'password1234', 'passw0rd123',
        '1234567890', '12345678910', '0123456789', '1111111111', '0000000000', 'qwertyuiop',
        'qwerty1234', 'qwerty12345', 'iloveyou12', 'letmein123', 'welcome123', 'admin12345',
        'abcdefghij', 'abc1234567', 'football12', 'baseball12', 'monkey1234', 'dragon1234',
        'diablo1234', 'diablo12345', 'hellfire123', 'diabloiii1', 'sunshine12', 'princess12',
        'trustno1234', 'starwars12', 'whatever12', 'changeme123', 'administrator',
    ];

    public function __construct(private readonly int $minLength = 10, private readonly int $maxLength = 256)
    {
    }

    public function minLength(): int
    {
        return $this->minLength;
    }

    public function maxLength(): int
    {
        return PasswordHasher::usesBcrypt() ? min($this->maxLength, 72) : $this->maxLength;
    }

    /**
     * @return list<string> human-readable problems; empty when acceptable
     */
    public function validate(string $password, string $username = '', string $email = ''): array
    {
        $length = mb_strlen($password, 'UTF-8');
        if (!mb_check_encoding($password, 'UTF-8')) {
            return ['Password contains invalid characters.'];
        }
        if ($length < $this->minLength) {
            return ["Use at least {$this->minLength} characters. A passphrase of several words works well."];
        }
        if ($length > $this->maxLength || (PasswordHasher::usesBcrypt() && strlen($password) > 72)) {
            return ['That password is too long. Use at most ' . $this->maxLength() . ' characters.'];
        }
        $lower = mb_strtolower($password, 'UTF-8');
        if (in_array($lower, self::COMMON, true) || preg_match('/^(.)\1+$/u', $password)) {
            return ['That password is too common. Choose something less predictable.'];
        }
        if ($username !== '' && $lower === mb_strtolower($username)) {
            return ['Your password must not match your username.'];
        }
        if ($email !== '' && $lower === mb_strtolower(trim($email))) {
            return ['Your password must not match your email address.'];
        }
        return [];
    }
}
