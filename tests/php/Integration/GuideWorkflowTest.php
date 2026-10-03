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
        self::assertSame('purge', $workflow->adminAction($admin, $repo->find($id), 'purge', '', 'shopping-in-hellfire'));
        $workflow->purgeRow($id);
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
}
