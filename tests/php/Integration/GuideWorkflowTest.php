<?php

declare(strict_types=1);

namespace Uvs\Tests\Integration;

use Uvs\Support\ValidationException;

final class GuideWorkflowTest extends DatabaseTestCase
{
    private function content(string $title = 'Shopping in Hellfire', string $body = ''): array
    {
        return [
            'title' => $title,
            'summary' => 'How to shop efficiently for premium items in Hellfire.',
            'body' => $body !== '' ? $body : "## Start\n\n" . str_repeat('Griswold restocks when you level up. ', 10),
            'applies_to' => 'hellfire',
        ];
    }

    public function testFullLifecycleWithRevisions(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $author = $this->user('Author');
        $admin = $this->user('Boss', 'active', 'admin');

        $id = $workflow->createDraft($author, $this->content(), 25);
        $guide = $repo->find($id);
        self::assertSame('shopping-in-hellfire', $guide['slug']);
        self::assertSame('draft', $guide['review_status']);
        self::assertNull($repo->findPublishedBySlug('shopping-in-hellfire'), 'drafts are never public');

        $workflow->submit($author, $guide);
        $guide = $repo->find($id);
        self::assertSame('in_review', $guide['review_status']);
        self::assertCount(1, $repo->revisions($id));
        self::assertSame('submission', $repo->revisions($id)[0]['kind']);

        $workflow->adminAction($admin, $guide, 'request_changes', 'Add a section on Wirt.');
        $guide = $repo->find($id);
        self::assertSame('needs_changes', $guide['review_status']);
        self::assertSame('Add a section on Wirt.', $guide['moderation_note']);

        $guide = $workflow->saveDraft($guide, $this->content('Shopping in Hellfire', "## Start\n\n" . str_repeat('Wirt sells one item. ', 20)), (int) $guide['lock_version']);
        $workflow->submit($author, $guide);
        $guide = $repo->find($id);
        self::assertSame('resubmission', $repo->revisions($id)[0]['kind']);
        self::assertNull($guide['moderation_note']);

        $workflow->adminAction($admin, $guide, 'approve_publish');
        $published = $repo->findPublishedBySlug('shopping-in-hellfire');
        self::assertNotNull($published);
        self::assertStringContainsString('Wirt sells one item.', $published['body']);
        $guide = $repo->find($id);
        self::assertNotNull($guide['first_published_at']);
        self::assertSame(2, count($repo->revisions($id)), 'identical content reuses the submitted revision');

        // An update draft never changes the public version.
        $guide = $workflow->saveDraft($guide, $this->content('Renamed Title', "## Start\n\nSECRET DRAFT TEXT " . str_repeat('x ', 120)), (int) $guide['lock_version']);
        self::assertSame('draft', $guide['review_status']);
        self::assertSame('shopping-in-hellfire', $guide['slug'], 'slugs are fixed after publication');
        $public = $repo->findPublishedBySlug('shopping-in-hellfire');
        self::assertStringNotContainsString('SECRET DRAFT TEXT', $public['body']);
        self::assertSame('Shopping in Hellfire', $public['title']);

        $workflow->adminAction($admin, $repo->find($id), 'hide');
        self::assertNull($repo->findPublishedBySlug('shopping-in-hellfire'));
        $workflow->adminAction($admin, $repo->find($id), 'restore');
        self::assertNotNull($repo->findPublishedBySlug('shopping-in-hellfire'));
        $workflow->adminAction($admin, $repo->find($id), 'delete');
        self::assertNull($repo->findPublishedBySlug('shopping-in-hellfire'));
        $workflow->adminAction($admin, $repo->find($id), 'undelete');
        self::assertNotNull($repo->findPublishedBySlug('shopping-in-hellfire'));

        $actions = array_column($this->db->all("SELECT action FROM audit_events WHERE target_type = 'guide' ORDER BY id"), 'action');
        self::assertSame(['guide.changes_requested', 'guide.approved', 'guide.published', 'guide.hidden', 'guide.restored', 'guide.deleted', 'guide.undeleted'], $actions);
    }

    public function testPurgeRequiresSoftDeleteAndExactSlug(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $author = $this->user('Author');
        $admin = $this->user('Boss', 'active', 'admin');
        $id = $workflow->createDraft($author, $this->content(), 25);
        try {
            $workflow->adminAction($admin, $repo->find($id), 'purge', '', 'shopping-in-hellfire');
            self::fail('Purge must require a soft delete first');
        } catch (ValidationException) {
        }
        $workflow->adminAction($admin, $repo->find($id), 'delete');
        try {
            $workflow->adminAction($admin, $repo->find($id), 'purge', '', 'wrong-slug');
            self::fail('Purge must require the exact slug');
        } catch (ValidationException) {
        }
        $result = $workflow->adminAction($admin, $repo->find($id), 'purge', '', 'shopping-in-hellfire');
        self::assertStringContainsString('permanently deleted', $result['message']);
        self::assertSame([], $result['media']);
        self::assertNull($repo->find($id));
    }

    public function testSlugCollisionsAndReservedSlugs(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $author = $this->user('Author');
        $first = $repo->find($workflow->createDraft($author, $this->content('Shopping'), 25));
        self::assertNotSame('shopping', $first['slug'], 'the curated /guides/shopping/ directory wins');
        $a = $repo->find($workflow->createDraft($author, $this->content('Warrior Build'), 25));
        $b = $repo->find($workflow->createDraft($author, $this->content('Warrior Build'), 25));
        self::assertSame('warrior-build', $a['slug']);
        self::assertSame('warrior-build-2', $b['slug']);
        $c = $repo->find($workflow->createDraft($author, $this->content('Admin'), 25));
        self::assertNotSame('admin', $c['slug']);
    }

    public function testStaleSaveIsRejected(): void
    {
        $workflow = $this->application->guideWorkflow();
        $author = $this->user('Author');
        $guide = $this->application->guides()->find($workflow->createDraft($author, $this->content(), 25));
        $workflow->saveDraft($guide, $this->content('First edit'), (int) $guide['lock_version']);
        try {
            $workflow->saveDraft($guide, $this->content('Stale edit'), (int) $guide['lock_version']);
            self::fail('Expected a conflict');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('conflict', $error->errors);
        }
    }

    public function testSubmissionRules(): void
    {
        $workflow = $this->application->guideWorkflow();
        $author = $this->user('Author');
        $admin = $this->user('Boss', 'active', 'admin');
        $short = $this->application->guides()->find($workflow->createDraft($author, ['title' => 'Hi', 'summary' => '', 'body' => 'tiny', 'applies_to' => null], 25));
        try {
            $workflow->submit($author, $short);
            self::fail('Too-short guides cannot be submitted');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('body', $error->errors);
        }
        $this->application->settings()->set('guide_submissions_enabled', '0', (int) $admin['id']);
        $guide = $this->application->guides()->find($workflow->createDraft($author, $this->content(), 25));
        $this->expectException(ValidationException::class);
        $workflow->submit($author, $guide);
    }

    public function testDraftLimit(): void
    {
        $workflow = $this->application->guideWorkflow();
        $author = $this->user('Author');
        $workflow->createDraft($author, $this->content('One'), 2);
        $workflow->createDraft($author, $this->content('Two'), 2);
        $this->expectException(ValidationException::class);
        $workflow->createDraft($author, $this->content('Three'), 2);
    }

    public function testAdminEditCreatesRevisionAndProtectsPublishedSlug(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $author = $this->user('Author');
        $admin = $this->user('Boss', 'active', 'admin');
        $id = $workflow->createDraft($author, $this->content(), 25);
        $workflow->adminEdit($admin, $repo->find($id), $this->content('Edited by admin'), 'custom-slug', 'Fixed typos');
        $guide = $repo->find($id);
        self::assertSame('custom-slug', $guide['slug']);
        self::assertSame('admin_edit', $repo->revisions($id)[0]['kind']);
        $workflow->submit($author, $guide);
        $workflow->adminAction($admin, $repo->find($id), 'approve_publish');
        $this->expectException(ValidationException::class);
        $workflow->adminEdit($admin, $repo->find($id), $this->content('Edited by admin'), 'another-slug', '');
    }

    /**
     * @return array{0: int, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function submitted(string $title = 'Shopping in Hellfire', string $body = ''): array
    {
        $workflow = $this->application->guideWorkflow();
        $author = $this->user('Author' . bin2hex(random_bytes(3)));
        $id = $workflow->createDraft($author, $this->content($title, $body), 25);
        $workflow->submit($author, $this->application->guides()->find($id));
        return [$id, $author, (array) $this->application->guides()->find($id)];
    }

    private function assertConflict(callable $action, string $message): void
    {
        try {
            $action();
            self::fail($message);
        } catch (ValidationException $error) {
            self::assertArrayHasKey('conflict', $error->errors, $message . ': ' . $error->first());
        }
    }

    public function testConcurrentPublishAndRejectCannotBothApply(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $first = $this->user('FirstAdmin', 'active', 'admin');
        $second = $this->user('SecondAdmin', 'active', 'admin');
        [$id, , $seen] = $this->submitted();

        // Both administrators opened the same review page (same version).
        $workflow->adminAction($first, $seen, 'approve_publish', '', '', (int) $seen['lock_version']);
        $this->assertConflict(fn () => $workflow->adminAction($second, $seen, 'reject', 'Not useful', '', (int) $seen['lock_version']),
            'a stale rejection must not overwrite a publication');
        $guide = $repo->find($id);
        self::assertSame('approved', $guide['review_status']);
        self::assertSame('published', $guide['visibility']);
        self::assertNotNull($repo->findPublishedBySlug((string) $guide['slug']));
        self::assertNull($guide['moderation_note']);
        $actions = array_column($this->db->all("SELECT action FROM audit_events WHERE target_type = 'guide' ORDER BY id"), 'action');
        self::assertSame(['guide.approved', 'guide.published'], $actions, 'the refused decision leaves no audit trail');

        // And the other way round: a rejection first, then a stale publication.
        [$id, , $seen] = $this->submitted('Warrior Build');
        $workflow->adminAction($second, $seen, 'reject', 'Off topic', '', (int) $seen['lock_version']);
        $this->assertConflict(fn () => $workflow->adminAction($first, $seen, 'approve_publish', '', '', (int) $seen['lock_version']),
            'a stale publication must not resurrect a rejected guide');
        $guide = $repo->find($id);
        self::assertSame('rejected', $guide['review_status']);
        self::assertNull($guide['published_revision_id']);
        self::assertNull($guide['first_published_at']);
        self::assertNull($repo->findPublishedBySlug((string) $guide['slug']));
    }

    public function testStaleVersionIsRejectedEvenWhenTheActionIsStillValid(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $admin = $this->user('Boss', 'active', 'admin');
        [$id, , $seen] = $this->submitted();
        // Any change bumps the version; a decision based on the old version is refused,
        // even though "approve_publish" would still be allowed in the current state.
        $workflow->adminAction($admin, $seen, 'request_changes', 'Add detail', '', (int) $seen['lock_version']);
        $this->assertConflict(fn () => $workflow->adminAction($admin, $seen, 'request_changes', 'Different note', '', (int) $seen['lock_version']),
            'a stale version must be refused');
        self::assertSame('Add detail', $repo->find($id)['moderation_note']);
        // A request without an explicit version uses the version of the row the caller loaded.
        $this->assertConflict(fn () => $workflow->adminAction($admin, $seen, 'reject', 'x'), 'the loaded row is the expected version');
        // The current version is accepted.
        $current = $repo->find($id);
        $workflow->adminAction($admin, $current, 'reject', 'Final', '', (int) $current['lock_version']);
        self::assertSame('rejected', $repo->find($id)['review_status']);
        // Admin edits are versioned too.
        $this->assertConflict(fn () => $workflow->adminEdit($admin, $current, $this->content('Edited'), (string) $current['slug'], '', (int) $current['lock_version']),
            'a stale admin edit must be refused');
    }

    public function testWithdrawAndPublishRaceKeepsAConsistentState(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $admin = $this->user('Boss', 'active', 'admin');

        // Publication wins: the author's withdrawal (based on the in-review version) is refused.
        [$id, , $seen] = $this->submitted();
        $workflow->adminAction($admin, $seen, 'approve_publish', '', '', (int) $seen['lock_version']);
        $this->assertConflict(fn () => $workflow->withdraw($seen, (int) $seen['lock_version']), 'withdrawing after publication');
        try {
            $workflow->withdraw($seen); // without a version the fresh, locked row decides
            self::fail('A published guide cannot be withdrawn');
        } catch (ValidationException) {
        }
        $guide = $repo->find($id);
        self::assertSame('approved', $guide['review_status']);
        self::assertNotNull($repo->findPublishedBySlug((string) $guide['slug']));

        // Withdrawal wins: the administrator's publication (based on the in-review version) is refused.
        [$id, , $seen] = $this->submitted('Warrior Build');
        $workflow->withdraw($seen, (int) $seen['lock_version']);
        $this->assertConflict(fn () => $workflow->adminAction($admin, $seen, 'approve_publish', '', '', (int) $seen['lock_version']),
            'publishing a withdrawn submission');
        $guide = $repo->find($id);
        self::assertSame('draft', $guide['review_status']);
        self::assertNull($guide['published_revision_id']);
        self::assertNull($repo->findPublishedBySlug((string) $guide['slug']));
    }

    public function testPublishedRevisionIsAlwaysTheContentTheModeratorReviewed(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $admin = $this->user('Boss', 'active', 'admin');
        [$id, $author, $guide] = $this->submitted();
        $workflow->adminAction($admin, $guide, 'approve', '', '', (int) $guide['lock_version']);
        $reviewed = $repo->find($id); // the administrator's "publish" page

        // Meanwhile the author withdraws, rewrites, and resubmits.
        $workflow->withdraw($reviewed, (int) $reviewed['lock_version']);
        $draft = $repo->find($id);
        $draft = $workflow->saveDraft($draft, $this->content('Shopping in Hellfire', "## Start\n\nUNREVIEWED TEXT " . str_repeat('y ', 120)), (int) $draft['lock_version']);
        $workflow->submit($author, $draft, (int) $draft['lock_version']);

        $this->assertConflict(fn () => $workflow->adminAction($admin, $reviewed, 'publish', '', '', (int) $reviewed['lock_version']),
            'publishing content that changed after review');
        $guide = $repo->find($id);
        self::assertNull($guide['published_revision_id']);
        self::assertNull($repo->findPublishedBySlug((string) $guide['slug']));

        // Publishing the current version snapshots exactly the locked row.
        $workflow->adminAction($admin, $guide, 'approve_publish', '', '', (int) $guide['lock_version']);
        $guide = $repo->find($id);
        $published = $repo->revision($id, (int) $guide['published_revision_id']);
        self::assertSame($guide['body'], $published['body']);
        self::assertStringContainsString('UNREVIEWED TEXT', $repo->findPublishedBySlug((string) $guide['slug'])['body']);
        self::assertTrue(hash_equals((string) $published['content_hash'], \Uvs\Guides\GuideWorkflow::contentHash($guide)));
    }

    public function testModerationWaitsForARowLockHeldByAnotherRequest(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $admin = $this->user('Boss', 'active', 'admin');
        [$id, , $seen] = $this->submitted();
        $other = \Uvs\Database::connect($this->application->config);
        $other->pdo()->beginTransaction();
        $other->value('SELECT id FROM guides WHERE id = :id FOR UPDATE', ['id' => $id]);
        $this->db->pdo()->exec('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $workflow->adminAction($admin, $seen, 'reject', 'x', '', (int) $seen['lock_version']);
            self::fail('The action must wait for the competing transaction');
        } catch (\PDOException $error) {
            self::assertStringContainsString('1205', (string) $error->getMessage() . $error->getCode() . json_encode($error->errorInfo));
        } finally {
            // The competing request publishes and commits.
            $other->execute("UPDATE guides SET lock_version = lock_version + 1 WHERE id = :id", ['id' => $id]);
            $other->pdo()->commit();
            $this->db->pdo()->exec('SET SESSION innodb_lock_wait_timeout = 50');
        }
        self::assertSame('in_review', $repo->find($id)['review_status'], 'the timed-out action changed nothing');
        $this->assertConflict(fn () => $workflow->adminAction($admin, $seen, 'reject', 'x', '', (int) $seen['lock_version']),
            'after the competing commit the old version is stale');
    }

    private function attachImage(int $guideId, int $ownerId): string
    {
        $publicId = substr(strtr(base64_encode(random_bytes(16)), '+/', '-_'), 0, 22);
        $this->db->execute(
            "INSERT INTO media (public_id, owner_id, purpose, guide_id, mime_type, extension, width, height, byte_size, sha256, created_at)
             VALUES (:public, :owner, 'guide', :guide, 'image/webp', 'webp', 10, 10, 100, :sha, UTC_TIMESTAMP())",
            ['public' => $publicId, 'owner' => $ownerId, 'guide' => $guideId, 'sha' => str_repeat('a', 64)],
        );
        return $publicId;
    }

    public function testSubmittedOrPublishedMediaCannotBeDeleted(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $media = $this->application->media();
        $admin = $this->user('Boss', 'active', 'admin');
        $author = $this->user('Author');
        $id = $workflow->createDraft($author, $this->content(), 25);
        $image = $this->attachImage($id, (int) $author['id']);
        $spare = $this->attachImage($id, (int) $author['id']);
        $draft = $repo->find($id);
        $draft = $workflow->saveDraft($draft, $this->content('Shopping in Hellfire', "## Start\n\n![Shop](media:{$image}) " . str_repeat('z ', 120)), (int) $draft['lock_version']);
        $workflow->submit($author, $draft, (int) $draft['lock_version']);

        // Under review: the submitted version's image is protected; unused images are not.
        try {
            $media->deleteGuideImage($author, $image, false);
            self::fail('An image in the submitted version must not be deletable');
        } catch (ValidationException $error) {
            self::assertStringContainsString('waiting for review', $error->first());
        }
        $media->deleteGuideImage($author, $spare, false);
        self::assertNull($this->db->one('SELECT id FROM media WHERE public_id = :id', ['id' => $spare]));

        // Approved but not yet published: still protected.
        $guide = $repo->find($id);
        $workflow->adminAction($admin, $guide, 'approve', '', '', (int) $guide['lock_version']);
        $this->expectDeletionRefused($author, $image);

        // Published: protected by the published revision even after the working copy drops it.
        $guide = $repo->find($id);
        $workflow->adminAction($admin, $guide, 'publish', '', '', (int) $guide['lock_version']);
        $guide = $repo->find($id);
        $workflow->saveDraft($guide, $this->content('Shopping in Hellfire', "## Start\n\n" . str_repeat('no image ', 40)), (int) $guide['lock_version']);
        $this->expectDeletionRefused($author, $image);
        self::assertNotNull($this->db->one('SELECT id FROM media WHERE public_id = :id', ['id' => $image]));
    }

    public function testWithdrawnSubmissionMediaCanBeDeletedAgain(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $author = $this->user('Author');
        $id = $workflow->createDraft($author, $this->content(), 25);
        $image = $this->attachImage($id, (int) $author['id']);
        $draft = $repo->find($id);
        $draft = $workflow->saveDraft($draft, $this->content('Shopping in Hellfire', "## Start\n\n![Shop](media:{$image}) " . str_repeat('z ', 120)), (int) $draft['lock_version']);
        $workflow->submit($author, $draft, (int) $draft['lock_version']);
        $this->expectDeletionRefused($author, $image);
        $workflow->withdraw($repo->find($id));
        $this->application->media()->deleteGuideImage($author, $image, false);
        self::assertNull($this->db->one('SELECT id FROM media WHERE public_id = :id', ['id' => $image]));
        $withdrawn = $repo->find($id);
        try {
            $workflow->submit($author, $withdrawn, (int) $withdrawn['lock_version']);
            self::fail('A deleted image must not be accepted in a new submission');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('body', $error->errors);
        }
        $revised = $workflow->saveDraft($withdrawn,
            $this->content('Shopping in Hellfire', "## Start\n\n" . str_repeat('revised ', 40)),
            (int) $withdrawn['lock_version']);
        $workflow->submit($author, $revised, (int) $revised['lock_version']);
        self::assertSame('in_review', $repo->find($id)['review_status']);
    }

    public function testRequestedChangesCannotDeleteAnImageStillInTheWorkingCopy(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $author = $this->user('Author');
        $admin = $this->user('Boss', 'active', 'admin');
        $id = $workflow->createDraft($author, $this->content(), 25);
        $image = $this->attachImage($id, (int) $author['id']);
        $draft = $repo->find($id);
        $body = "## Start\n\n![Shop](media:{$image}) " . str_repeat('z ', 120);
        $draft = $workflow->saveDraft($draft, $this->content('Shopping in Hellfire', $body), (int) $draft['lock_version']);
        $workflow->submit($author, $draft, (int) $draft['lock_version']);
        $submitted = $repo->find($id);
        $workflow->adminAction($admin, $submitted, 'request_changes', 'Revise the text.', '', (int) $submitted['lock_version']);

        $this->expectDeletionRefused($author, $image);
        $changes = $repo->find($id);
        $workflow->saveDraft($changes, $this->content('Shopping in Hellfire', "## Start\n\n" . str_repeat('revised ', 40)),
            (int) $changes['lock_version']);
        $this->application->media()->deleteGuideImage($author, $image, false);
        self::assertNull($this->db->one('SELECT id FROM media WHERE public_id = :id', ['id' => $image]));
    }

    public function testDraftImagesOfAPublishedGuideAreNotPublic(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $media = $this->application->media();
        $admin = $this->user('Boss', 'active', 'admin');
        $author = $this->user('Author');
        $id = $workflow->createDraft($author, $this->content(), 25);
        $old = $this->attachImage($id, (int) $author['id']);
        $draft = $repo->find($id);
        $draft = $workflow->saveDraft($draft, $this->content('Shopping in Hellfire', "## Start\n\n![Old](media:{$old}) " . str_repeat('z ', 120)), (int) $draft['lock_version']);
        $workflow->submit($author, $draft, (int) $draft['lock_version']);
        $guide = $repo->find($id);
        $workflow->adminAction($admin, $guide, 'approve_publish', '', '', (int) $guide['lock_version']);
        self::assertTrue(\Uvs\Media\MediaService::isPublic($media->findPublic($old, 'webp')));

        $new = $this->attachImage($id, (int) $author['id']);
        $guide = $repo->find($id);
        $guide = $workflow->saveDraft($guide, $this->content('Shopping in Hellfire', "## Start\n\n![Old](media:{$old}) ![New](media:{$new}) " . str_repeat('z ', 120)), (int) $guide['lock_version']);
        self::assertFalse(\Uvs\Media\MediaService::isPublic($media->findPublic($new, 'webp')), 'draft image of a published guide');
        $workflow->submit($author, $guide, (int) $guide['lock_version']);
        self::assertFalse(\Uvs\Media\MediaService::isPublic($media->findPublic($new, 'webp')), 'submitted but unpublished image');
        $guide = $repo->find($id);
        $workflow->adminAction($admin, $guide, 'approve_publish', '', '', (int) $guide['lock_version']);
        self::assertTrue(\Uvs\Media\MediaService::isPublic($media->findPublic($new, 'webp')));
        self::assertTrue(\Uvs\Media\MediaService::isPublic($media->findPublic($old, 'webp')));

        $guide = $repo->find($id);
        $workflow->adminAction($admin, $guide, 'hide', '', '', (int) $guide['lock_version']);
        self::assertFalse(\Uvs\Media\MediaService::isPublic($media->findPublic($new, 'webp')), 'hidden guide');
    }

    /**
     * @param array<string, mixed> $author
     */
    private function expectDeletionRefused(array $author, string $image): void
    {
        try {
            $this->application->media()->deleteGuideImage($author, $image, false);
            self::fail('The image must not be deletable');
        } catch (ValidationException) {
        }
        self::assertNotNull($this->db->one('SELECT id FROM media WHERE public_id = :id', ['id' => $image]));
    }
}
