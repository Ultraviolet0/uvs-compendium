<?php

declare(strict_types=1);

namespace Uvs\Security;

/**
 * Synchronizer-token CSRF protection. One random token per session, rotated on
 * authentication changes, compared in constant time.
 */
final class Csrf
{
    public const FIELD = '_csrf';
    public const HEADER = 'HTTP_X_CSRF_TOKEN';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::FIELD);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::FIELD, $token);
        }
        return $token;
    }

    public function rotate(): void
    {
        $this->session->set(self::FIELD, bin2hex(random_bytes(32)));
    }

    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function isValid(?string $submitted): bool
    {
        $expected = $this->session->get(self::FIELD);
        return is_string($submitted) && is_string($expected) && strlen($expected) === 64
            && hash_equals($expected, $submitted);
    }
}
