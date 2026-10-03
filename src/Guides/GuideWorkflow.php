<?php

declare(strict_types=1);

namespace Uvs\Guides;

use PDOException;
use Uvs\Admin\AuditLog;
use Uvs\Admin\Settings;
use Uvs\Database;
use Uvs\Support\Text;
use Uvs\Support\ValidationException;

/**
 * Every guide state transition lives here, for authors and administrators alike.
 * Controllers authorise the actor through the Gate first; this class enforces
 * which transitions are valid from the guide's current state.
 */
final class GuideWorkflow
{
    public const APPLIES_TO = ['diablo' => 'Diablo', 'hellfire' => 'Hellfire', 'both' => 'Diablo and Hellfire'];
    public const TITLE_MAX = 140;
    public const SUMMARY_MAX = 400;
    public const SUBMIT_TITLE_MIN = 5;
    public const SUBMIT_SUMMARY_MIN = 20;
    public const SUBMIT_BODY_MIN = 200;

    public function __construct(
        private readonly Database $db,
        private readonly GuideRepository $guides,
        private readonly AuditLog $audit,
        private readonly Settings $settings,
        private readonly string $appRoot,
        private readonly int $maxBodyLength = 60000,
    ) {
    }

    public function maxBodyLength(): int
    {
        return $this->maxBodyLength;
    }

    /**
     * @param array<string, mixed> $input raw form input
     * @return array{title: string, summary: string, body: string, applies_to: ?string}
     * @throws ValidationException
     */
    public function clean(array $input): array
    {
        $title = Text::line($input['title'] ?? '', self::TITLE_MAX);
        $summary = Text::line($input['summary'] ?? '', self::SUMMARY_MAX);
        $body = Text::block($input['body'] ?? '', $this->maxBodyLength);
        $applies = is_string($input['applies_to'] ?? null) && isset(self::APPLIES_TO[$input['applies_to']]) ? $input['applies_to'] : null;
        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Give your guide a title.';
        } elseif (mb_strlen($title) > self::TITLE_MAX) {
            $errors['title'] = 'Keep the title under ' . self::TITLE_MAX . ' characters.';
        }
        if (mb_strlen($summary) > self::SUMMARY_MAX) {
            $errors['summary'] = 'Keep the summary under ' . self::SUMMARY_MAX . ' characters.';
        }
        if (mb_strlen($body) > $this->maxBodyLength) {
            $errors['body'] = 'The guide body is limited to ' . number_format($this->maxBodyLength) . ' characters.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return ['title' => $title, 'summary' => $summary, 'body' => $body, 'applies_to' => $applies];
    }

    /**
     * @param array<string, mixed> $author
     * @param array{title: string, summary: string, body: string, applies_to: ?string} $content
     */
    public function createDraft(array $author, array $content, int $maxDrafts): int
    {
        if ($this->guides->countDraftsForAuthor((int) $author['id']) >= $maxDrafts) {
            throw new ValidationException(['title' => "You can keep up to {$maxDrafts} unpublished guides. Finish or delete one first."]);
        }
        $now = Database::now();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $this->db->execute(
                    'INSERT INTO guides (author_id, slug, title, summary, body, applies_to, created_at, updated_at)
                     VALUES (:author, :slug, :title, :summary, :body, :applies, :now, :now)',
                    [
                        'author' => (int) $author['id'],
                        'slug' => $this->uniqueSlug(Slugger::slugify($content['title'])),
                        'title' => $content['title'],
                        'summary' => $content['summary'],
                        'body' => $content['body'],
                        'applies' => $content['applies_to'],
                        'now' => $now,
                    ],
                );
                return $this->db->lastInsertId();
            } catch (PDOException $error) {
                if ($error->getCode() !== '23000') {
                    throw $error;
                }
            }
        }
        throw new ValidationException(['title' => 'Could not reserve a URL for this guide. Try a different title.']);
    }

    /**
     * Saves the author's working copy with optimistic locking.
     *
     * @param array<string, mixed> $guide
     * @param array{title: string, summary: string, body: string, applies_to: ?string} $content
     * @return array<string, mixed> the updated guide
     */
    public function saveDraft(array $guide, array $content, int $lockVersion): array
    {
        if (!GuideStatus::authorCanEdit($guide)) {
            throw new ValidationException(['form' => 'This guide cannot be edited in its current state.']);
        }
        if ($lockVersion !== (int) $guide['lock_version']) {
            throw new ValidationException(['conflict' => 'This guide was changed in another window or by an administrator. Reload to see the latest version before saving.']);
        }
        $slug = $guide['first_published_at'] === null && $content['title'] !== $guide['title']
            ? $this->uniqueSlug(Slugger::slugify($content['title']), (int) $guide['id'])
            : (string) $guide['slug'];
        // Editing a published (or rejected) update starts a fresh draft; the public snapshot is unaffected.
        $review = in_array($guide['review_status'], ['approved', 'rejected'], true) ? 'draft' : $guide['review_status'];
        $updated = $this->db->execute(
            'UPDATE guides SET title = :title, summary = :summary, body = :body, applies_to = :applies, slug = :slug,
                               review_status = :review, lock_version = lock_version + 1, updated_at = :now
             WHERE id = :id AND lock_version = :lock',
            [
                'title' => $content['title'], 'summary' => $content['summary'], 'body' => $content['body'],
                'applies' => $content['applies_to'], 'slug' => $slug, 'review' => $review,
                'now' => Database::now(), 'id' => (int) $guide['id'], 'lock' => $lockVersion,
            ],
        );
        if ($updated !== 1) {
            throw new ValidationException(['conflict' => 'This guide was changed elsewhere. Reload before saving again.']);
        }
        return (array) $this->guides->find((int) $guide['id']);
    }

    /**
     * @param array<string, mixed> $author
     * @param array<string, mixed> $guide
     */
    public function submit(array $author, array $guide): void
    {
        if (!$this->settings->bool('guide_submissions_enabled')) {
            throw new ValidationException(['form' => 'Guide submissions are paused right now. Your draft is saved; please try again later.']);
        }
        if (!GuideStatus::authorCanSubmit($guide)) {
            throw new ValidationException(['form' => 'This guide cannot be submitted in its current state.']);
        }
        $errors = [];
        if (mb_strlen((string) $guide['title']) < self::SUBMIT_TITLE_MIN) {
            $errors['title'] = 'Use a title of at least ' . self::SUBMIT_TITLE_MIN . ' characters before submitting.';
        }
        if (mb_strlen((string) $guide['summary']) < self::SUBMIT_SUMMARY_MIN) {
            $errors['summary'] = 'Add a summary of at least ' . self::SUBMIT_SUMMARY_MIN . ' characters before submitting.';
        }
        if (mb_strlen(trim((string) $guide['body'])) < self::SUBMIT_BODY_MIN) {
            $errors['body'] = 'The guide body needs at least ' . self::SUBMIT_BODY_MIN . ' characters before it can be reviewed.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->db->transaction(function () use ($author, $guide): void {
            $kind = $guide['submitted_at'] === null ? 'submission' : 'resubmission';
            $this->snapshot($guide, $kind, (int) $author['id'], null);
            $this->db->execute(
                "UPDATE guides SET review_status = 'in_review', submitted_at = :now, moderation_note = NULL,
                                   lock_version = lock_version + 1, updated_at = :now WHERE id = :id",
                ['now' => Database::now(), 'id' => (int) $guide['id']],
            );
        });
    }

    /**
     * @param array<string, mixed> $guide
     */
    public function withdraw(array $guide): void
    {
        if (!GuideStatus::authorCanWithdraw($guide)) {
            throw new ValidationException(['form' => 'Only guides waiting for review can be withdrawn.']);
        }
        $this->db->execute(
            "UPDATE guides SET review_status = 'draft', lock_version = lock_version + 1, updated_at = :now WHERE id = :id",
            ['now' => Database::now(), 'id' => (int) $guide['id']],
        );
    }

    // ---- Administrative actions -------------------------------------------------

    public const ADMIN_ACTIONS = [
        'approve' => 'Approve',
        'publish' => 'Publish',
        'approve_publish' => 'Approve and publish',
        'request_changes' => 'Request changes',
        'reject' => 'Reject',
        'hide' => 'Hide (unpublish)',
        'restore' => 'Restore to public',
        'delete' => 'Move to deleted',
        'undelete' => 'Recover from deleted',
        'purge' => 'Delete permanently',
    ];

    /**
     * Lists the actions valid for a guide's current state.
     *
     * @param array<string, mixed> $guide
     * @return list<string>
     */
    public function availableActions(array $guide): array
    {
        if (!empty($guide['deleted_at'])) {
            return ['undelete', 'purge'];
        }
        $review = $guide['review_status'];
        $actions = [];
        if ($review === 'in_review') {
            $actions = ['approve_publish', 'approve', 'request_changes', 'reject'];
        } elseif ($review === 'approved' && $guide['visibility'] !== 'published') {
            $actions = ['publish', 'request_changes', 'reject'];
        } elseif ($review === 'needs_changes') {
            $actions = ['reject'];
        }
        if ($guide['visibility'] === 'published') {
            $actions[] = 'hide';
        } elseif ($guide['visibility'] === 'hidden' && $guide['published_revision_id'] !== null) {
            $actions[] = 'restore';
        }
        $actions[] = 'delete';
        return $actions;
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $guide
     */
    public function adminAction(array $actor, array $guide, string $action, string $note = '', string $confirmation = ''): string
    {
        if (!in_array($action, $this->availableActions($guide), true)) {
            throw new ValidationException(['action' => 'That action is not available for this guide right now.']);
        }
        $note = trim($note);
        if (mb_strlen($note) > 2000) {
            throw new ValidationException(['note' => 'Keep the note under 2,000 characters.']);
        }
        $id = (int) $guide['id'];
        $now = Database::now();
        $label = (string) $guide['title'];
        $actorId = (int) $actor['id'];
        $message = '';

        switch ($action) {
            case 'approve':
                $this->db->execute("UPDATE guides SET review_status = 'approved', reviewed_at = :now, reviewed_by = :actor,
                    moderation_note = :note, lock_version = lock_version + 1, updated_at = :now WHERE id = :id",
                    ['now' => $now, 'actor' => $actorId, 'note' => $note === '' ? null : $note, 'id' => $id]);
                $this->audit->record($actor, 'guide.approved', 'guide', $id, $label);
                $message = 'Guide approved. Publish it when you are ready.';
                break;
            case 'publish':
            case 'approve_publish':
                $this->publish($actor, $guide, $note);
                if ($action === 'approve_publish') {
                    $this->audit->record($actor, 'guide.approved', 'guide', $id, $label);
                }
                $this->audit->record($actor, 'guide.published', 'guide', $id, $label, ['slug' => $guide['slug']]);
                $message = 'Guide published.';
                break;
            case 'request_changes':
                if ($note === '') {
                    throw new ValidationException(['note' => 'Explain what the author should change.']);
                }
                $this->db->execute("UPDATE guides SET review_status = 'needs_changes', reviewed_at = :now, reviewed_by = :actor,
                    moderation_note = :note, lock_version = lock_version + 1, updated_at = :now WHERE id = :id",
                    ['now' => $now, 'actor' => $actorId, 'note' => $note, 'id' => $id]);
                $this->audit->record($actor, 'guide.changes_requested', 'guide', $id, $label, ['note' => $note]);
                $message = 'Changes requested. The author can revise and resubmit.';
                break;
            case 'reject':
                $this->db->execute("UPDATE guides SET review_status = 'rejected', reviewed_at = :now, reviewed_by = :actor,
                    moderation_note = :note, lock_version = lock_version + 1, updated_at = :now WHERE id = :id",
                    ['now' => $now, 'actor' => $actorId, 'note' => $note === '' ? null : $note, 'id' => $id]);
                $this->audit->record($actor, 'guide.rejected', 'guide', $id, $label, $note === '' ? [] : ['note' => $note]);
                $message = 'Guide rejected.';
                break;
            case 'hide':
                $this->db->execute("UPDATE guides SET visibility = 'hidden', updated_at = :now WHERE id = :id", ['now' => $now, 'id' => $id]);
                $this->audit->record($actor, 'guide.hidden', 'guide', $id, $label, $note === '' ? [] : ['note' => $note]);
                $message = 'Guide hidden from readers. Restore it at any time.';
                break;
            case 'restore':
                $this->db->execute("UPDATE guides SET visibility = 'published', updated_at = :now WHERE id = :id AND published_revision_id IS NOT NULL",
                    ['now' => $now, 'id' => $id]);
                $this->audit->record($actor, 'guide.restored', 'guide', $id, $label);
                $message = 'Guide restored to public view.';
                break;
            case 'delete':
                $this->db->execute('UPDATE guides SET deleted_at = :now, deleted_by = :actor, updated_at = :now WHERE id = :id',
                    ['now' => $now, 'actor' => $actorId, 'id' => $id]);
                $this->audit->record($actor, 'guide.deleted', 'guide', $id, $label);
                $message = 'Guide moved to Deleted. It is no longer visible and can be recovered.';
                break;
            case 'undelete':
                $this->db->execute('UPDATE guides SET deleted_at = NULL, deleted_by = NULL, updated_at = :now WHERE id = :id',
                    ['now' => $now, 'id' => $id]);
                $this->audit->record($actor, 'guide.undeleted', 'guide', $id, $label);
                $message = 'Guide recovered with its previous state.';
                break;
            case 'purge':
                if (!hash_equals((string) $guide['slug'], trim($confirmation))) {
                    throw new ValidationException(['confirmation' => 'Type the guide’s slug exactly to confirm permanent deletion.']);
                }
                $this->audit->record($actor, 'guide.purged', 'guide', $id, $label, ['slug' => $guide['slug']]);
                $message = 'purge';
                break;
        }
        return $message;
    }

    /**
     * Permanently removes a guide row after its media files are deleted by the caller.
     */
    public function purgeRow(int $guideId): void
    {
        $this->db->transaction(function () use ($guideId): void {
            $this->db->execute("UPDATE guides SET published_revision_id = NULL, visibility = 'private' WHERE id = :id", ['id' => $guideId]);
            $this->db->execute('DELETE FROM guides WHERE id = :id', ['id' => $guideId]);
        });
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $guide
     */
    private function publish(array $actor, array $guide, string $note): void
    {
        $this->db->transaction(function () use ($actor, $guide, $note): void {
            $latest = $this->guides->latestRevision((int) $guide['id']);
            $revisionId = $latest !== null && hash_equals((string) $latest['content_hash'], self::contentHash($guide))
                ? (int) $latest['id']
                : $this->snapshot($guide, 'publication', (int) $actor['id'], $note === '' ? null : $note);
            $now = Database::now();
            $this->db->execute(
                "UPDATE guides SET review_status = 'approved', visibility = 'published', published_revision_id = :revision,
                    reviewed_at = :now, reviewed_by = :actor, moderation_note = :note, published_at = :now,
                    first_published_at = COALESCE(first_published_at, :now), lock_version = lock_version + 1, updated_at = :now
                 WHERE id = :id",
                ['revision' => $revisionId, 'now' => $now, 'actor' => (int) $actor['id'],
                 'note' => $note === '' ? null : $note, 'id' => (int) $guide['id']],
            );
        });
    }

    /**
     * Administrator edit of the working copy; recorded as a revision.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $guide
     * @param array{title: string, summary: string, body: string, applies_to: ?string} $content
     */
    public function adminEdit(array $actor, array $guide, array $content, string $slug, string $note): void
    {
        if (!empty($guide['deleted_at'])) {
            throw new ValidationException(['form' => 'Recover the guide before editing it.']);
        }
        $slug = trim($slug);
        $slugChanged = $slug !== '' && $slug !== $guide['slug'];
        if ($slugChanged) {
            if ($guide['first_published_at'] !== null) {
                throw new ValidationException(['slug' => 'A guide’s URL is fixed once it has been published.']);
            }
            if (!Slugger::isValid($slug)) {
                throw new ValidationException(['slug' => 'Use lowercase letters, numbers, and single hyphens.']);
            }
            if (Slugger::isReserved($slug, $this->appRoot) || $this->guides->slugTaken($slug, (int) $guide['id'])) {
                throw new ValidationException(['slug' => 'That URL is reserved or already in use.']);
            }
        }
        $this->db->transaction(function () use ($actor, $guide, $content, $slug, $slugChanged, $note): void {
            $this->db->execute(
                'UPDATE guides SET title = :title, summary = :summary, body = :body, applies_to = :applies, slug = :slug,
                                   lock_version = lock_version + 1, updated_at = :now WHERE id = :id',
                [
                    'title' => $content['title'], 'summary' => $content['summary'], 'body' => $content['body'],
                    'applies' => $content['applies_to'], 'slug' => $slugChanged ? $slug : $guide['slug'],
                    'now' => Database::now(), 'id' => (int) $guide['id'],
                ],
            );
            $updated = array_merge($guide, $content);
            $latest = $this->guides->latestRevision((int) $guide['id']);
            if ($latest === null || !hash_equals((string) $latest['content_hash'], self::contentHash($updated))) {
                $this->snapshot($updated, 'admin_edit', (int) $actor['id'], trim($note) === '' ? null : mb_substr(trim($note), 0, 500));
            }
        });
        $this->audit->record($actor, 'guide.admin_edited', 'guide', (int) $guide['id'], $content['title']);
        if ($slugChanged) {
            $this->audit->record($actor, 'guide.slug_changed', 'guide', (int) $guide['id'], $content['title'],
                ['from' => $guide['slug'], 'to' => $slug]);
        }
    }

    /**
     * Stores an immutable revision of the guide's working copy.
     *
     * @param array<string, mixed> $guide
     */
    private function snapshot(array $guide, string $kind, ?int $createdBy, ?string $note): int
    {
        $number = (int) $this->db->value('SELECT COALESCE(MAX(revision_number), 0) + 1 FROM guide_revisions WHERE guide_id = :id FOR UPDATE',
            ['id' => (int) $guide['id']]);
        $this->db->execute(
            'INSERT INTO guide_revisions (guide_id, revision_number, kind, title, summary, body, applies_to, content_hash, created_by, note, created_at)
             VALUES (:guide, :number, :kind, :title, :summary, :body, :applies, :hash, :by, :note, :now)',
            [
                'guide' => (int) $guide['id'], 'number' => $number, 'kind' => $kind,
                'title' => (string) $guide['title'], 'summary' => (string) $guide['summary'], 'body' => (string) $guide['body'],
                'applies' => $guide['applies_to'] ?? null, 'hash' => self::contentHash($guide),
                'by' => $createdBy, 'note' => $note, 'now' => Database::now(),
            ],
        );
        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $guide
     */
    public static function contentHash(array $guide): string
    {
        return hash('sha256', json_encode([
            (string) $guide['title'], (string) $guide['summary'], (string) $guide['body'], $guide['applies_to'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function uniqueSlug(string $base, ?int $exceptId = null): string
    {
        $base = Slugger::isValid($base) ? $base : 'guide';
        if (Slugger::isReserved($base, $this->appRoot)) {
            $base = substr($base, 0, Slugger::MAX_LENGTH - 10) . '-guide';
        }
        $candidate = $base;
        for ($n = 2; $this->guides->slugTaken($candidate, $exceptId) || Slugger::isReserved($candidate, $this->appRoot); $n++) {
            $suffix = '-' . $n;
            $candidate = rtrim(substr($base, 0, Slugger::MAX_LENGTH - strlen($suffix)), '-') . $suffix;
            if ($n > 500) {
                $candidate = substr($base, 0, 60) . '-' . bin2hex(random_bytes(4));
                break;
            }
        }
        return $candidate;
    }
}
