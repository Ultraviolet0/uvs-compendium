<?php

declare(strict_types=1);

namespace Uvs\Users;

/**
 * Conservative public usernames: 3-24 ASCII letters, digits, underscores, or
 * hyphens, starting with a letter or digit. Uniqueness is case-insensitive while
 * the chosen display casing is preserved.
 */
final class UsernamePolicy
{
    public const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{2,23}$/';

    private const RESERVED = [
        'abuse', 'account', 'accounts', 'admin', 'administrator', 'anonymous', 'api', 'calculators',
        'compendium', 'everyone', 'guest', 'guides', 'help', 'hostmaster', 'info', 'login', 'logout',
        'mail', 'me', 'media', 'member', 'members', 'mod', 'moderator', 'moderators', 'new', 'no-reply',
        'noreply', 'null', 'official', 'owner', 'postmaster', 'privacy', 'reference', 'root', 'security',
        'signup', 'staff', 'support', 'sysop', 'system', 'team', 'test', 'ultraviolet', 'undefined',
        'uv', 'uvs', 'webmaster', 'www',
    ];

    /** Fragments that would let a name impersonate site staff. */
    private const RESERVED_FRAGMENTS = ['admin', 'moderator', 'ultraviolet', 'compendium', 'official', 'sysop'];

    public static function normalize(string $username): string
    {
        return strtolower(trim($username));
    }

    /**
     * @return list<string>
     */
    public static function validate(string $username, bool $allowReserved = false): array
    {
        if (!preg_match(self::PATTERN, $username)) {
            return ['Usernames are 3–24 characters: letters, numbers, underscores, or hyphens, starting with a letter or number.'];
        }
        if (!$allowReserved && self::isReserved($username)) {
            return ['That username is reserved. Please choose another.'];
        }
        return [];
    }

    public static function isReserved(string $username): bool
    {
        $key = self::normalize($username);
        if (in_array($key, self::RESERVED, true)) {
            return true;
        }
        $squashed = str_replace(['_', '-'], '', $key);
        foreach (self::RESERVED_FRAGMENTS as $fragment) {
            if (str_contains($squashed, $fragment)) {
                return true;
            }
        }
        return false;
    }
}
