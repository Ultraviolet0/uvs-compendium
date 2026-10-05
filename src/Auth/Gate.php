<?php

declare(strict_types=1);

namespace Uvs\Auth;

/**
 * Central permission decisions. Controllers ask the gate; they never compare
 * usernames or scatter role checks. Roles: member, moderator (reserved for a
 * future phase, guide moderation only), admin.
 */
final class Gate
{
    /**
     * @param array<string, mixed>|null $user
     * @param array<string, mixed>|null $subject
     */
    public function allows(?array $user, string $ability, ?array $subject = null): bool
    {
        if ($user === null) {
            return false;
        }
        $status = (string) $user['status'];
        $role = (string) $user['role'];
        $active = $status === 'active';
        $signedIn = in_array($status, ['pending', 'active'], true);
        $isAdmin = $active && $role === 'admin';
        $owns = $subject !== null && isset($subject['author_id']) && (int) $subject['author_id'] === (int) $user['id'];

        return match ($ability) {
            'account.manage' => $signedIn,
            'guide.create', 'media.upload' => $active,
            'guide.submit' => $active && ($subject === null || $owns),
            'guide.edit' => $active && $owns,
            'guide.view_private' => ($signedIn && $owns) || $this->allows($user, 'guides.moderate'),
            'guides.moderate' => $active && in_array($role, ['admin', 'moderator'], true),
            'admin.access' => $active && in_array($role, ['admin', 'moderator'], true),
            'users.manage', 'settings.manage', 'audit.view', 'guides.purge' => $isAdmin,
            default => false,
        };
    }
}
