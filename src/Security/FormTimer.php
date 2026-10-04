<?php

declare(strict_types=1);

namespace Uvs\Security;

/**
 * Signed form-render timestamps: a cheap signal against bots that submit a
 * form faster than a person could read it. It supplements, never replaces,
 * Turnstile and server-side validation.
 */
final class FormTimer
{
    public const FIELD = 'form_started';

    public function __construct(private readonly string $key)
    {
    }

    public function issue(string $form, ?int $now = null): string
    {
        $time = (string) ($now ?? time());
        return $time . '.' . $this->sign($form, $time);
    }

    /**
     * @return 'ok'|'too_fast'|'expired'|'invalid'
     */
    public function check(string $form, mixed $value, int $minSeconds, int $maxAge, ?int $now = null): string
    {
        if (!is_string($value) || !preg_match('/^(\d{9,12})\.([a-f0-9]{64})$/', $value, $match)) {
            return 'invalid';
        }
        if (!hash_equals($this->sign($form, $match[1]), $match[2])) {
            return 'invalid';
        }
        $elapsed = ($now ?? time()) - (int) $match[1];
        if ($elapsed < 0) {
            return 'invalid';
        }
        if ($elapsed < $minSeconds) {
            return 'too_fast';
        }
        return $elapsed > $maxAge ? 'expired' : 'ok';
    }

    private function sign(string $form, string $time): string
    {
        return hash_hmac('sha256', $form . '|' . $time, $this->key);
    }
}
