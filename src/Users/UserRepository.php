<?php

declare(strict_types=1);

namespace Uvs\Users;

use Uvs\Database;

final class UserRepository
{
    public const STATUSES = ['pending', 'active', 'rejected', 'suspended'];
    public const ROLES = ['member', 'moderator', 'admin'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    /**
     * Account row plus profile fields and avatar media for display.
     *
     * @return array<string, mixed>|null
     */
    public function findWithProfile(int $id): ?array
    {
        return $this->db->one(
            'SELECT u.*, p.bio, p.preferred_game, p.website_url, p.discord_handle, p.avatar_media_id,
                    m.public_id AS avatar_public_id, m.extension AS avatar_extension
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN media m ON m.id = p.avatar_media_id
             WHERE u.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE username_key = :key', ['key' => UsernamePolicy::normalize($username)]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE email_key = :key', ['key' => EmailAddress::normalize($email)]);
    }

    /**
     * Finds an account by username or email address for sign-in.
     *
     * @return array<string, mixed>|null
     */
    public function findByLogin(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || mb_strlen($identifier) > 254) {
            return null;
        }
        return str_contains($identifier, '@') ? $this->findByEmail($identifier) : $this->findByUsername($identifier);
    }

    public function usernameTaken(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    public function emailTaken(string $email, ?int $exceptUserId = null): bool
    {
        $row = $this->findByEmail($email);
        return $row !== null && (int) $row['id'] !== $exceptUserId;
    }

    public function create(string $username, string $email, string $passwordHash, string $status, string $role = 'member'): int
    {
        $now = Database::now();
        $this->db->execute(
            'INSERT INTO users (username, username_key, email, email_key, password_hash, role, status, status_changed_at, created_at, updated_at)
             VALUES (:username, :username_key, :email, :email_key, :hash, :role, :status, :now, :now, :now)',
            [
                'username' => $username,
                'username_key' => UsernamePolicy::normalize($username),
                'email' => trim($email),
                'email_key' => EmailAddress::normalize($email),
                'hash' => $passwordHash,
                'role' => $role,
                'status' => $status,
                'now' => $now,
            ],
        );
        $id = $this->db->lastInsertId();
        $this->db->execute('INSERT INTO user_profiles (user_id, updated_at) VALUES (:id, :now)', ['id' => $id, 'now' => $now]);
        return $id;
    }

    public function updatePasswordHash(int $id, string $hash, bool $revokeSessions): void
    {
        $this->db->execute(
            'UPDATE users SET password_hash = :hash, updated_at = :now'
            . ($revokeSessions ? ', auth_epoch = auth_epoch + 1' : '') . ' WHERE id = :id',
            ['hash' => $hash, 'now' => Database::now(), 'id' => $id],
        );
    }

    public function revokeSessions(int $id): void
    {
        $this->db->execute('UPDATE users SET auth_epoch = auth_epoch + 1, updated_at = :now WHERE id = :id',
            ['now' => Database::now(), 'id' => $id]);
    }

    public function countAdmins(bool $activeOnly = true): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM users WHERE role = 'admin'" . ($activeOnly ? " AND status = 'active'" : ''),
        );
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->all('SELECT status, COUNT(*) AS total FROM users GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    /**
     * Administrative listing with optional search and filters.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function search(string $query, ?string $status, ?string $role, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $where[] = '(u.username_key LIKE :q OR u.email_key LIKE :q)';
            $params['q'] = '%' . addcslashes(mb_strtolower($query), '%_\\') . '%';
        }
        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }
        if ($role !== null && in_array($role, self::ROLES, true)) {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(*) FROM users u {$clause}", $params);
        $rows = $this->db->all(
            "SELECT u.*,
                    (SELECT COUNT(*) FROM guides g WHERE g.author_id = u.id AND g.deleted_at IS NULL AND g.submitted_at IS NOT NULL) AS submitted_count,
                    (SELECT COUNT(*) FROM guides g WHERE g.author_id = u.id AND g.deleted_at IS NULL AND g.visibility = 'published') AS published_count
             FROM users u {$clause}
             ORDER BY FIELD(u.status, 'pending', 'active', 'suspended', 'rejected'), u.created_at DESC
             LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => max(0, ($page - 1) * $perPage)],
        );
        return ['rows' => $rows, 'total' => $total];
    }
}
