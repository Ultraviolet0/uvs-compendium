<?php

declare(strict_types=1);

namespace Uvs\Guides;

use Uvs\Database;

final class GuideRepository
{
    public const ADMIN_FILTERS = [
        'queue' => 'In review',
        'needs_changes' => 'Needs changes',
        'approved' => 'Approved, unpublished',
        'published' => 'Published',
        'hidden' => 'Hidden',
        'rejected' => 'Rejected',
        'drafts' => 'Drafts',
        'deleted' => 'Deleted',
        'all' => 'All',
    ];

    private const AUTHOR_COLUMNS = 'u.username AS author_username, u.status AS author_status,
        m.public_id AS author_avatar_public_id, m.extension AS author_avatar_extension';

    private const AUTHOR_JOINS = 'LEFT JOIN users u ON u.id = g.author_id
        LEFT JOIN user_profiles p ON p.user_id = u.id
        LEFT JOIN media m ON m.id = p.avatar_media_id';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT g.*, ' . self::AUTHOR_COLUMNS . ' FROM guides g ' . self::AUTHOR_JOINS . ' WHERE g.id = :id', ['id' => $id]);
    }

    /**
     * A published guide with its published snapshot, or null when it must not be public.
     *
     * @return array<string, mixed>|null
     */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->one(
            'SELECT g.id, g.slug, g.author_id, g.first_published_at, g.published_at,
                    r.title, r.summary, r.body, r.applies_to, r.created_at AS revision_created_at, ' . self::AUTHOR_COLUMNS . '
             FROM guides g
             JOIN guide_revisions r ON r.id = g.published_revision_id AND r.guide_id = g.id
             ' . self::AUTHOR_JOINS . "
             WHERE g.slug = :slug AND g.visibility = 'published' AND g.deleted_at IS NULL",
            ['slug' => $slug],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublished(int $limit = 50, int $offset = 0, ?int $authorId = null): array
    {
        $params = ['limit' => $limit, 'offset' => $offset];
        $author = '';
        if ($authorId !== null) {
            $author = 'AND g.author_id = :author';
            $params['author'] = $authorId;
        }
        return $this->db->all(
            'SELECT g.id, g.slug, g.author_id, g.first_published_at, g.published_at, r.title, r.summary, r.applies_to, '
            . self::AUTHOR_COLUMNS . '
             FROM guides g
             JOIN guide_revisions r ON r.id = g.published_revision_id
             ' . self::AUTHOR_JOINS . "
             WHERE g.visibility = 'published' AND g.deleted_at IS NULL {$author}
             ORDER BY g.first_published_at DESC, g.id DESC
             LIMIT :limit OFFSET :offset",
            $params,
        );
    }

    public function countPublished(?int $authorId = null): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM guides WHERE visibility = 'published' AND deleted_at IS NULL AND published_revision_id IS NOT NULL"
            . ($authorId !== null ? ' AND author_id = :author' : ''),
            $authorId !== null ? ['author' => $authorId] : [],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAuthor(int $authorId): array
    {
        return $this->db->all(
            'SELECT g.*, (SELECT COUNT(*) FROM media WHERE media.guide_id = g.id) AS image_count
             FROM guides g WHERE g.author_id = :author AND g.deleted_at IS NULL ORDER BY g.updated_at DESC',
            ['author' => $authorId],
        );
    }

    public function countDraftsForAuthor(int $authorId): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM guides WHERE author_id = :author AND deleted_at IS NULL AND first_published_at IS NULL",
            ['author' => $authorId],
        );
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function adminList(string $filter, string $query, int $page, int $perPage): array
    {
        $where = match ($filter) {
            'queue' => "g.review_status = 'in_review' AND g.deleted_at IS NULL",
            'needs_changes' => "g.review_status = 'needs_changes' AND g.deleted_at IS NULL",
            'approved' => "g.review_status = 'approved' AND g.visibility = 'private' AND g.deleted_at IS NULL",
            'published' => "g.visibility = 'published' AND g.deleted_at IS NULL",
            'hidden' => "g.visibility = 'hidden' AND g.deleted_at IS NULL",
            'rejected' => "g.review_status = 'rejected' AND g.deleted_at IS NULL",
            'drafts' => "g.review_status = 'draft' AND g.visibility = 'private' AND g.deleted_at IS NULL",
            'deleted' => 'g.deleted_at IS NOT NULL',
            default => '1 = 1',
        };
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $where .= ' AND (g.title LIKE :q OR g.slug LIKE :q OR u.username_key LIKE :q)';
            $params['q'] = '%' . addcslashes(mb_strtolower($query), '%_\\') . '%';
        }
        $order = $filter === 'queue' ? 'g.submitted_at ASC' : 'g.updated_at DESC';
        $total = (int) $this->db->value('SELECT COUNT(*) FROM guides g LEFT JOIN users u ON u.id = g.author_id WHERE ' . $where, $params);
        $rows = $this->db->all(
            'SELECT g.*, ' . self::AUTHOR_COLUMNS . ' FROM guides g ' . self::AUTHOR_JOINS . " WHERE {$where}
             ORDER BY {$order} LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => max(0, ($page - 1) * $perPage)],
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @return array<string, int>
     */
    public function adminCounts(): array
    {
        $row = $this->db->one(
            "SELECT
                SUM(review_status = 'in_review' AND deleted_at IS NULL) AS queue,
                SUM(review_status = 'needs_changes' AND deleted_at IS NULL) AS needs_changes,
                SUM(review_status = 'approved' AND visibility = 'private' AND deleted_at IS NULL) AS approved,
                SUM(visibility = 'published' AND deleted_at IS NULL) AS published,
                SUM(visibility = 'hidden' AND deleted_at IS NULL) AS hidden,
                SUM(review_status = 'rejected' AND deleted_at IS NULL) AS rejected,
                SUM(review_status = 'draft' AND visibility = 'private' AND deleted_at IS NULL) AS drafts,
                SUM(deleted_at IS NOT NULL) AS deleted,
                COUNT(*) AS `all`
             FROM guides",
        ) ?? [];
        return array_map('intval', array_map(static fn ($value) => $value ?? 0, $row));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revisions(int $guideId): array
    {
        return $this->db->all(
            'SELECT r.id, r.guide_id, r.revision_number, r.kind, r.title, r.content_hash, r.note, r.created_at, r.created_by,
                    u.username AS created_by_username
             FROM guide_revisions r LEFT JOIN users u ON u.id = r.created_by
             WHERE r.guide_id = :id ORDER BY r.revision_number DESC',
            ['id' => $guideId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function revision(int $guideId, int $revisionId): ?array
    {
        return $this->db->one(
            'SELECT r.*, u.username AS created_by_username FROM guide_revisions r LEFT JOIN users u ON u.id = r.created_by
             WHERE r.guide_id = :guide AND r.id = :id',
            ['guide' => $guideId, 'id' => $revisionId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function previousRevision(int $guideId, int $revisionNumber): ?array
    {
        return $this->db->one(
            'SELECT * FROM guide_revisions WHERE guide_id = :guide AND revision_number < :number ORDER BY revision_number DESC LIMIT 1',
            ['guide' => $guideId, 'number' => $revisionNumber],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestRevision(int $guideId): ?array
    {
        return $this->db->one('SELECT * FROM guide_revisions WHERE guide_id = :id ORDER BY revision_number DESC LIMIT 1', ['id' => $guideId]);
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        $id = $this->db->value('SELECT id FROM guides WHERE slug = :slug', ['slug' => $slug]);
        return $id !== null && (int) $id !== $exceptId;
    }

    /**
     * Media metadata for rendering a guide's images.
     *
     * @return array<string, array{extension: string, width: int, height: int}>
     */
    public function mediaMap(int $guideId): array
    {
        $map = [];
        foreach ($this->db->all('SELECT public_id, extension, width, height FROM media WHERE guide_id = :id', ['id' => $guideId]) as $row) {
            $map[(string) $row['public_id']] = [
                'extension' => (string) $row['extension'],
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
            ];
        }
        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function media(int $guideId): array
    {
        return $this->db->all('SELECT * FROM media WHERE guide_id = :id ORDER BY created_at', ['id' => $guideId]);
    }
}
