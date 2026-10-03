<?php

declare(strict_types=1);

namespace Uvs\Admin;

use Uvs\Database;
use Uvs\Logger;

/**
 * Append-only record of privileged actions. Metadata is filtered so passwords,
 * tokens, secrets, and session identifiers can never be recorded.
 */
final class AuditLog
{
    public const LABELS = [
        'user.registered' => 'Account registered',
        'user.approved' => 'Account approved',
        'user.rejected' => 'Account rejected',
        'user.suspended' => 'Account suspended',
        'user.reactivated' => 'Account reactivated',
        'user.role_changed' => 'Role changed',
        'user.note_updated' => 'Admin note updated',
        'user.deleted' => 'Account deleted',
        'user.password_reset_by_cli' => 'Password set from the command line',
        'user.mfa_disabled_by_cli' => 'Two-factor authentication removed from the command line',
        'admin.bootstrapped' => 'First administrator created',
        'guide.approved' => 'Guide approved',
        'guide.published' => 'Guide published',
        'guide.changes_requested' => 'Changes requested',
        'guide.rejected' => 'Guide rejected',
        'guide.hidden' => 'Guide hidden',
        'guide.restored' => 'Guide restored',
        'guide.deleted' => 'Guide moved to deleted',
        'guide.undeleted' => 'Guide recovered from deleted',
        'guide.purged' => 'Guide permanently deleted',
        'guide.admin_edited' => 'Guide edited by administrator',
        'guide.slug_changed' => 'Guide slug changed',
        'setting.changed' => 'Setting changed',
        'media.deleted_by_admin' => 'Media deleted by administrator',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{id: int|string, username: string}|null $actor
     * @param array<string, mixed> $metadata
     */
    public function record(?array $actor, string $action, string $targetType, ?int $targetId, ?string $targetLabel, array $metadata = []): void
    {
        $this->db->execute(
            'INSERT INTO audit_events (actor_id, actor_label, action, target_type, target_id, target_label, metadata, created_at)
             VALUES (:actor, :label, :action, :type, :target, :target_label, :metadata, :now)',
            [
                'actor' => $actor !== null ? (int) $actor['id'] : null,
                'label' => $actor !== null ? mb_substr((string) $actor['username'], 0, 40) : 'System',
                'action' => $action,
                'type' => $targetType,
                'target' => $targetId,
                'target_label' => $targetLabel !== null ? mb_substr($targetLabel, 0, 160) : null,
                'metadata' => $metadata === [] ? null : json_encode(Logger::redact($metadata), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'now' => Database::now(),
            ],
        );
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function page(int $page, int $perPage, ?string $action = null, ?string $targetType = null): array
    {
        $where = [];
        $params = [];
        if ($action !== null && $action !== '') {
            $where[] = 'action = :action';
            $params['action'] = $action;
        }
        if ($targetType !== null && $targetType !== '') {
            $where[] = 'target_type = :type';
            $params['type'] = $targetType;
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(*) FROM audit_events {$clause}", $params);
        $rows = $this->db->all(
            "SELECT * FROM audit_events {$clause} ORDER BY id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => max(0, ($page - 1) * $perPage)],
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forTarget(string $targetType, int $targetId, int $limit = 25): array
    {
        return $this->db->all(
            'SELECT * FROM audit_events WHERE target_type = :type AND target_id = :id ORDER BY id DESC LIMIT :limit',
            ['type' => $targetType, 'id' => $targetId, 'limit' => $limit],
        );
    }
}
