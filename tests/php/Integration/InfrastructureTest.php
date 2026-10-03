<?php

declare(strict_types=1);

namespace Uvs\Tests\Integration;

use Uvs\Console\Migrator;
use Uvs\Media\MediaService;
use Uvs\Support\ValidationException;

final class InfrastructureTest extends DatabaseTestCase
{
    public function testMigrationsAreRecordedAndRepeatable(): void
    {
        $migrator = new Migrator($this->db, $this->application->root . '/migrations');
        self::assertSame([], $migrator->migrate());
        foreach ($migrator->status() as $row) {
            self::assertSame('applied', $row['state'], $row['version']);
        }
    }

    public function testNamedPlaceholdersMayRepeat(): void
    {
        self::assertSame(2, (int) $this->db->value('SELECT :n + :n', ['n' => 1]));
    }

    public function testRateLimiterCountsAndStoresNoRawIdentifiers(): void
    {
        $limiter = $this->application->rateLimiter();
        for ($i = 0; $i < 8; $i++) {
            self::assertTrue($limiter->hit('login.account', 'Victim@Example.test'));
        }
        self::assertFalse($limiter->hit('login.account', 'victim@example.test'));
        self::assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM rate_limits WHERE bucket LIKE '%victim%'"));
        $limiter->clear('login.account', 'victim@example.test');
        self::assertTrue($limiter->hit('login.account', 'victim@example.test'));
    }

    public function testSettingsAreBoundedAndTyped(): void
    {
        $settings = $this->application->settings();
        self::assertFalse($settings->bool('auto_approve_accounts'));
        self::assertTrue($settings->bool('registrations_enabled'));
        $settings->set('media_quota_mb', '20', null);
        self::assertSame(20, $settings->int('media_quota_mb'));
        $this->expectException(\InvalidArgumentException::class);
        $settings->set('media_quota_mb', '999999', null);
    }

    public function testAuditMetadataNeverStoresSecrets(): void
    {
        $this->application->audit()->record(null, 'setting.changed', 'setting', null, 'x', ['password' => 'hunter2', 'session_id' => 'abc', 'from' => 'off']);
        $row = (string) $this->db->value('SELECT metadata FROM audit_events');
        self::assertStringNotContainsString('hunter2', $row);
        self::assertStringNotContainsString('abc', $row);
        self::assertStringContainsString('off', $row);
    }

    private function upload(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uvs-up-');
        file_put_contents($path, $contents);
        return $path;
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 7) {
            imageline($image, $x, 0, $width - $x, $height, imagecolorallocate($image, $x % 255, 90, 200));
        }
        ob_start();
        imagepng($image);
        return (string) ob_get_clean();
    }

    public function testMediaOwnershipCountAndQuota(): void
    {
        $media = $this->application->media();
        $author = $this->user('Painter');
        $intruder = $this->user('Intruder');
        $guideId = $this->application->guideWorkflow()->createDraft($author, ['title' => 'Pictures', 'summary' => '', 'body' => '', 'applies_to' => null], 25);
        $guide = $this->application->guides()->find($guideId);

        $stored = $media->storeGuideImage($author, $guide, $this->upload($this->png(400, 300)), 'Diagram');
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}$/', $stored['public_id']);
        self::assertFileExists($media->path($stored['public_id'], $stored['extension']));
        self::assertStringStartsWith($this->application->storagePath('media'), $media->path($stored['public_id'], $stored['extension']));

        try {
            $media->deleteGuideImage($intruder, $stored['public_id'], false);
            self::fail('Other members cannot delete the image');
        } catch (ValidationException) {
        }
        self::assertFileExists($media->path($stored['public_id'], $stored['extension']));

        $this->application->settings()->set('media_max_images_per_guide', '1', null);
        try {
            $media->storeGuideImage($author, $guide, $this->upload($this->png(50, 50)), '');
            self::fail('Per-guide count limit applies');
        } catch (ValidationException $error) {
            self::assertStringContainsString('maximum', $error->first());
        }

        $this->application->settings()->set('media_max_images_per_guide', '20', null);
        $this->application->settings()->set('media_quota_mb', '1', null);
        $this->db->execute('UPDATE media SET byte_size = 1048000 WHERE public_id = :id', ['id' => $stored['public_id']]);
        try {
            $media->storeGuideImage($author, $guide, $this->upload($this->png(200, 200)), '');
            self::fail('Quota applies');
        } catch (ValidationException $error) {
            self::assertStringContainsString('quota', $error->first());
        }

        $media->deleteGuideImage($author, $stored['public_id'], false);
        self::assertFileDoesNotExist($media->path($stored['public_id'], $stored['extension']));
    }

    public function testOrphanCleanupRemovesOnlyUnreferencedOldUploads(): void
    {
        $media = $this->application->media();
        $author = $this->user('Painter');
        $guideId = $this->application->guideWorkflow()->createDraft($author, ['title' => 'Pictures', 'summary' => '', 'body' => '', 'applies_to' => null], 25);
        $guide = $this->application->guides()->find($guideId);
        $used = $media->storeGuideImage($author, $guide, $this->upload($this->png(60, 60)), '');
        $unused = $media->storeGuideImage($author, $guide, $this->upload($this->png(61, 61)), '');
        $this->db->execute('UPDATE guides SET body = :b WHERE id = :id', ['b' => '![x](media:' . $used['public_id'] . ')', 'id' => $guideId]);
        self::assertSame([], $media->cleanupOrphans(), 'recent uploads are kept');
        $this->db->execute('UPDATE media SET created_at = UTC_TIMESTAMP() - INTERVAL 30 DAY');
        $removed = $media->cleanupOrphans();
        self::assertSame([$unused['public_id']], $removed);
        self::assertFileExists($media->path($used['public_id'], $used['extension']));
    }
}
