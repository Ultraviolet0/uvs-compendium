<?php

declare(strict_types=1);

namespace Uvs\Media;

use RuntimeException;
use Uvs\Admin\Settings;
use Uvs\Config;
use Uvs\Database;
use Uvs\Guides\MarkdownRenderer;
use Uvs\Logger;
use Uvs\Support\ValidationException;

/**
 * Stores processed images outside the document root under random, server-chosen
 * names, tracks ownership in the database, and enforces count and quota limits.
 */
final class MediaService
{
    public function __construct(
        private readonly Database $db,
        private readonly Config $config,
        private readonly Settings $settings,
        private readonly string $directory,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @return array{max_upload: int, quota: int, per_guide: int}
     */
    public function limits(): array
    {
        $mb = 1024 * 1024;
        return [
            'max_upload' => min((int) $this->config->get('media.max_upload_bytes'), $this->settings->int('media_max_upload_mb') * $mb,
                self::iniBytes((string) ini_get('upload_max_filesize'))),
            'quota' => min((int) $this->config->get('media.max_quota_bytes'), $this->settings->int('media_quota_mb') * $mb),
            'per_guide' => min((int) $this->config->get('media.max_images_per_guide'), $this->settings->int('media_max_images_per_guide')),
        ];
    }

    public function usage(int $userId): int
    {
        return (int) $this->db->value('SELECT COALESCE(SUM(byte_size), 0) FROM media WHERE owner_id = :id', ['id' => $userId]);
    }

    /**
     * Validates a PHP upload entry and returns its temporary path.
     *
     * @param array<string, mixed>|null $file
     */
    public function uploadedPath(?array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['image' => 'Choose an image to upload.']);
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(['image' => 'That image is too large. The limit is ' . ImageProcessor::megabytes($this->limits()['max_upload']) . '.']);
        }
        $path = (string) ($file['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $path === '' || !is_uploaded_file($path)) {
            throw new ValidationException(['image' => 'The upload did not complete. Please try again.']);
        }
        return $path;
    }

    /**
     * Replaces a member's avatar. The image is decoded and written without any
     * lock held; the quota check, the insert, and the swap then happen in one
     * transaction that holds the member's row, so parallel uploads cannot
     * overshoot the quota. The new file is removed again if that fails.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed> the stored media row
     */
    public function storeAvatar(array $user, string $path): array
    {
        $userId = (int) $user['id'];
        $previous = $this->currentAvatar($userId);
        $image = $this->processor()->process($path, 'avatar', (int) $this->config->get('media.avatar_dimension', 256));
        $this->assertQuota($userId, strlen($image['data']) - (int) ($previous['byte_size'] ?? 0)); // cheap early refusal
        [$publicId, $file] = $this->writeFile($image);
        try {
            [$media, $replaced] = $this->db->transaction(function () use ($userId, $image, $publicId): array {
                $this->lockOwner($userId);
                $previous = $this->currentAvatar($userId);
                $this->assertQuota($userId, strlen($image['data']) - (int) ($previous['byte_size'] ?? 0));
                $media = $this->insertRow($publicId, $userId, 'avatar', null, $image, null);
                $this->db->execute('UPDATE user_profiles SET avatar_media_id = :media, updated_at = :now WHERE user_id = :id',
                    ['media' => (int) $media['id'], 'now' => Database::now(), 'id' => $userId]);
                if ($previous !== null) {
                    $this->db->execute('DELETE FROM media WHERE id = :id', ['id' => (int) $previous['id']]);
                }
                return [$media, $previous];
            });
        } catch (\Throwable $error) {
            @unlink($file);
            throw $error;
        }
        if ($replaced !== null) {
            $this->unlinkFile($replaced);
        }
        return $media;
    }

    public function removeAvatar(int $userId): void
    {
        $previous = $this->db->transaction(function () use ($userId): ?array {
            $this->lockOwner($userId);
            $previous = $this->currentAvatar($userId);
            $this->db->execute('UPDATE user_profiles SET avatar_media_id = NULL, updated_at = :now WHERE user_id = :id',
                ['now' => Database::now(), 'id' => $userId]);
            if ($previous !== null) {
                $this->db->execute('DELETE FROM media WHERE id = :id', ['id' => (int) $previous['id']]);
            }
            return $previous;
        });
        if ($previous !== null) {
            $this->unlinkFile($previous);
        }
    }

    /**
     * Adds an image to a guide. As with avatars, decoding happens outside any
     * lock; the per-guide count and the owner's quota are then re-checked and
     * the row inserted while the guide and owner rows are locked (guide first,
     * matching the moderation workflow's lock order).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $guide
     * @return array<string, mixed>
     */
    public function storeGuideImage(array $user, array $guide, string $path, string $alt): array
    {
        $guideId = (int) $guide['id'];
        $ownerId = (int) $user['id'];
        $this->assertGuideCapacity($guideId); // cheap early refusal
        $image = $this->processor()->process($path, 'guide', (int) $this->config->get('media.max_guide_dimension', 1600));
        $this->assertQuota($ownerId, strlen($image['data']));
        [$publicId, $file] = $this->writeFile($image);
        try {
            return $this->db->transaction(function () use ($guideId, $ownerId, $image, $publicId, $alt): array {
                $locked = $this->db->one('SELECT id, deleted_at FROM guides WHERE id = :id FOR UPDATE', ['id' => $guideId]);
                if ($locked === null || $locked['deleted_at'] !== null) {
                    throw new ValidationException(['image' => 'This guide no longer accepts images.']);
                }
                $this->lockOwner($ownerId);
                $this->assertGuideCapacity($guideId);
                $this->assertQuota($ownerId, strlen($image['data']));
                return $this->insertRow($publicId, $ownerId, 'guide', $guideId, $image, $alt === '' ? null : mb_substr($alt, 0, 200));
            });
        } catch (\Throwable $error) {
            @unlink($file);
            throw $error;
        }
    }

    /**
     * Deletes a guide image. Only its owner (or an administrator) may delete it,
     * and never while the published version of its guide shows it or while a
     * submitted version awaiting review (or approved, awaiting publication)
     * does. The guide row is locked so the check cannot race a submission or
     * a publish; the file is removed only after the deletion has committed.
     *
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public function deleteGuideImage(array $actor, string $publicId, bool $asAdmin): array
    {
        $media = $this->db->transaction(function () use ($actor, $publicId, $asAdmin): array {
            $media = $this->db->one("SELECT * FROM media WHERE public_id = :id AND purpose = 'guide'", ['id' => $publicId]);
            if ($media === null || (!$asAdmin && (int) $media['owner_id'] !== (int) $actor['id'])) {
                throw new ValidationException(['image' => 'That image does not exist or is not yours.']);
            }
            if ($media['guide_id'] !== null) {
                $guide = $this->db->one('SELECT review_status, visibility, body, published_revision_id FROM guides WHERE id = :id FOR UPDATE',
                    ['id' => (int) $media['guide_id']]);
                $media = $this->db->one('SELECT * FROM media WHERE id = :id', ['id' => (int) $media['id']])
                    ?? throw new ValidationException(['image' => 'That image does not exist or is not yours.']);
                if ($guide !== null) {
                    $this->assertDeletable($guide, $publicId);
                }
            }
            $this->db->execute('DELETE FROM media WHERE id = :id', ['id' => (int) $media['id']]);
            return $media;
        });
        $this->unlinkFile($media);
        return $media;
    }

    /**
     * @param array<string, mixed> $guide the locked guide row
     */
    private function assertDeletable(array $guide, string $publicId): void
    {
        if ($guide['published_revision_id'] !== null) {
            $published = $this->db->value('SELECT body FROM guide_revisions WHERE id = :id', ['id' => (int) $guide['published_revision_id']]);
            if (is_string($published) && in_array($publicId, MarkdownRenderer::mediaReferences($published), true)) {
                throw new ValidationException(['image' => 'This image appears in the published version of the guide, so it cannot be deleted yet.']);
            }
        }
        // A request for changes leaves the submitted working copy in place. The
        // author must remove the reference before deleting its image, otherwise
        // the unchanged copy could be resubmitted with a broken image.
        $submitted = in_array($guide['review_status'], ['in_review', 'approved', 'needs_changes'], true);
        if ($submitted && in_array($publicId, MarkdownRenderer::mediaReferences((string) $guide['body']), true)) {
            throw new ValidationException(['image' => 'This image is part of the version waiting for review, so it cannot be deleted. Remove it from the guide text first.']);
        }
    }

    /**
     * Removes media rows (and their files) collected before their guide was deleted.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function deleteRows(array $rows): void
    {
        foreach ($rows as $row) {
            $this->deleteRow($row);
        }
    }

    /**
     * Removes abandoned uploads older than the grace period: detached rows,
     * guide images referenced by neither the working copy nor any revision,
     * superseded avatars, and stray files with no database row.
     *
     * @return list<string> public ids (or file names) removed
     */
    public function cleanupOrphans(bool $dryRun = false): array
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - 3600 * (int) $this->config->get('media.orphan_grace_hours', 24));
        $removed = [];
        $candidates = $this->db->all(
            "SELECT m.* FROM media m
             LEFT JOIN user_profiles p ON p.avatar_media_id = m.id
             WHERE m.created_at < :cutoff AND (m.purpose = 'guide' OR p.user_id IS NULL)",
            ['cutoff' => $cutoff],
        );
        foreach ($candidates as $media) {
            if ($media['purpose'] === 'guide' && $media['guide_id'] !== null && $this->isReferenced($media)) {
                continue;
            }
            $removed[] = (string) $media['public_id'];
            if (!$dryRun) {
                $this->deleteRow($media);
            }
        }
        if (is_dir($this->directory)) {
            $known = array_flip(array_map('strval', array_column($this->db->all('SELECT public_id FROM media'), 'public_id')));
            foreach (glob($this->directory . '/*/*') ?: [] as $file) {
                $id = pathinfo($file, PATHINFO_FILENAME);
                if (!isset($known[$id]) && is_file($file) && filemtime($file) < strtotime($cutoff . ' UTC')) {
                    $removed[] = basename($file);
                    if (!$dryRun) {
                        @unlink($file);
                    }
                }
            }
        }
        return $removed;
    }

    /**
     * @param array<string, mixed> $media
     */
    private function isReferenced(array $media): bool
    {
        $id = (string) $media['public_id'];
        $bodies = $this->db->all(
            'SELECT body FROM guides WHERE id = :id UNION ALL SELECT body FROM guide_revisions WHERE guide_id = :id',
            ['id' => (int) $media['guide_id']],
        );
        foreach ($bodies as $row) {
            if (str_contains((string) $row['body'], 'media:' . $id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Loads a media row with what is needed to decide whether it is public:
     * the owner's status, the guide's state, the body of the guide's current
     * published revision, and whether an avatar is still the owner's current one.
     *
     * @return array<string, mixed>|null
     */
    public function findPublic(string $publicId, string $extension): ?array
    {
        return $this->db->one(
            "SELECT m.*, u.status AS owner_status, g.visibility AS guide_visibility, g.deleted_at AS guide_deleted_at,
                    g.author_id AS guide_author_id, r.body AS published_body,
                    (p.avatar_media_id = m.id) AS is_current_avatar
             FROM media m
             JOIN users u ON u.id = m.owner_id
             LEFT JOIN user_profiles p ON p.user_id = m.owner_id
             LEFT JOIN guides g ON g.id = m.guide_id
             LEFT JOIN guide_revisions r ON r.id = g.published_revision_id AND r.guide_id = g.id
             WHERE m.public_id = :id AND m.extension = :ext",
            ['id' => $publicId, 'ext' => $extension],
        );
    }

    /**
     * Whether anyone may fetch this media: an active member's current avatar, or
     * an image that the current published revision of a live guide shows. Draft
     * images of a published guide stay private until a revision using them is published.
     *
     * @param array<string, mixed> $media a row from findPublic()
     */
    public static function isPublic(array $media): bool
    {
        if ($media['purpose'] === 'avatar') {
            return $media['owner_status'] === 'active' && (bool) $media['is_current_avatar'];
        }
        return $media['guide_id'] !== null
            && $media['guide_visibility'] === 'published'
            && $media['guide_deleted_at'] === null
            && is_string($media['published_body'])
            && in_array((string) $media['public_id'], MarkdownRenderer::mediaReferences($media['published_body']), true);
    }

    public function path(string $publicId, string $extension): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{22}$/', $publicId) || !in_array($extension, ['webp', 'jpg', 'png'], true)) {
            throw new RuntimeException('Invalid media identifier.');
        }
        return $this->directory . '/' . strtolower(substr($publicId, 0, 2)) . '/' . $publicId . '.' . $extension;
    }

    private function processor(): ImageProcessor
    {
        return new ImageProcessor(
            $this->limits()['max_upload'],
            (int) $this->config->get('media.max_processed_bytes'),
            (int) $this->config->get('media.max_source_pixels'),
        );
    }

    private function assertQuota(int $userId, int $additional): void
    {
        $quota = $this->limits()['quota'];
        if ($this->usage($userId) + $additional > $quota) {
            throw new ValidationException(['image' => 'This upload would exceed your image storage quota of ' . ImageProcessor::megabytes($quota) . '. Delete unused images first.']);
        }
    }

    private function assertGuideCapacity(int $guideId): void
    {
        $limit = $this->limits()['per_guide'];
        $count = (int) $this->db->value('SELECT COUNT(*) FROM media WHERE guide_id = :id', ['id' => $guideId]);
        if ($count >= $limit) {
            throw new ValidationException(['image' => $limit === 0
                ? 'Image uploads are currently disabled.'
                : "This guide already has the maximum of {$limit} images. Remove one first."]);
        }
    }

    /** Serialises quota decisions for one member (taken after any guide lock). */
    private function lockOwner(int $userId): void
    {
        $this->db->value('SELECT id FROM users WHERE id = :id FOR UPDATE', ['id' => $userId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentAvatar(int $userId): ?array
    {
        return $this->db->one('SELECT m.* FROM user_profiles p JOIN media m ON m.id = p.avatar_media_id WHERE p.user_id = :id', ['id' => $userId]);
    }

    /**
     * Writes a processed image under a fresh random name.
     *
     * @param array{data: string, mime: string, extension: string, width: int, height: int} $image
     * @return array{0: string, 1: string} public id and file path
     */
    private function writeFile(array $image): array
    {
        $publicId = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $path = $this->path($publicId, $image['extension']);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Media storage is not writable.');
        }
        $temporary = $directory . '/.' . $publicId . '.tmp';
        if (@file_put_contents($temporary, $image['data'], LOCK_EX) === false || !@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Media storage is not writable.');
        }
        @chmod($path, 0640);
        return [$publicId, $path];
    }

    /**
     * @param array{data: string, mime: string, extension: string, width: int, height: int} $image
     * @return array<string, mixed>
     */
    private function insertRow(string $publicId, int $ownerId, string $purpose, ?int $guideId, array $image, ?string $alt): array
    {
        $this->db->execute(
            'INSERT INTO media (public_id, owner_id, purpose, guide_id, mime_type, extension, width, height, byte_size, sha256, alt_text, created_at)
             VALUES (:public, :owner, :purpose, :guide, :mime, :ext, :width, :height, :bytes, :sha, :alt, :now)',
            [
                'public' => $publicId, 'owner' => $ownerId, 'purpose' => $purpose, 'guide' => $guideId,
                'mime' => $image['mime'], 'ext' => $image['extension'], 'width' => $image['width'], 'height' => $image['height'],
                'bytes' => strlen($image['data']), 'sha' => hash('sha256', $image['data']), 'alt' => $alt, 'now' => Database::now(),
            ],
        );
        return (array) $this->db->one('SELECT * FROM media WHERE public_id = :id', ['id' => $publicId]);
    }

    /**
     * @param array<string, mixed> $media
     */
    private function deleteRow(array $media): void
    {
        $this->db->execute('DELETE FROM media WHERE id = :id', ['id' => (int) $media['id']]);
        $this->unlinkFile($media);
    }

    /**
     * @param array<string, mixed> $media
     */
    private function unlinkFile(array $media): void
    {
        $path = $this->path((string) $media['public_id'], (string) $media['extension']);
        if (is_file($path) && !@unlink($path)) {
            $this->logger->warning('Could not remove a media file', ['media' => $media['public_id']]);
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number > 0 ? $number : PHP_INT_MAX,
        };
    }
}
