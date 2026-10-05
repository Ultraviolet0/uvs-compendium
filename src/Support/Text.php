<?php

declare(strict_types=1);

namespace Uvs\Support;

final class Text
{
    /** Normalises submitted single-line text: trims, removes control characters. */
    public static function line(mixed $value, int $max = 1000): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $value)), 0, $max + 1);
    }

    /** Normalises multi-line text: unifies newlines, removes other control characters. */
    public static function block(mixed $value, int $max = 100000): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return mb_substr(rtrim($value), 0, $max + 1);
    }

    public static function date(?string $value, string $format = 'F j, Y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format($format);
    }

    public static function isoDate(?string $value): string
    {
        return self::date($value, 'Y-m-d');
    }

    public static function dateTime(?string $value): string
    {
        return self::date($value, 'M j, Y H:i') . ($value ? ' UTC' : '');
    }

    public static function bytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 1) . ' MB';
        }
        return number_format(max(0, $bytes) / 1024, 0) . ' KB';
    }
}
