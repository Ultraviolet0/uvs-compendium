<?php

declare(strict_types=1);

namespace Uvs\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uvs\Config;
use Uvs\Support\PathGuard;

/**
 * Private paths (configuration, uploads, sessions, logs) must resolve outside
 * the web document root, including through symbolic links and for paths that
 * do not exist yet.
 */
final class PathGuardTest extends TestCase
{
    private string $base;
    private string $docroot;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/uvs-pathguard-' . bin2hex(random_bytes(6));
        $this->docroot = $this->base . '/public_html';
        mkdir($this->docroot . '/assets', 0700, true);
        mkdir($this->base . '/uvs-private/storage', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->base);
    }

    public function testSiblingPrivateDirectoryIsOutside(): void
    {
        self::assertTrue(PathGuard::isOutside($this->base . '/uvs-private/storage', $this->docroot));
        // Written relative to the document root, as the documentation suggests.
        self::assertTrue(PathGuard::isOutside($this->docroot . '/../uvs-private/storage', $this->docroot));
        // Not created yet: the nearest existing ancestor is resolved.
        self::assertTrue(PathGuard::isOutside($this->base . '/uvs-private/sessions/new', $this->docroot));
    }

    public function testDirectAndNestedPathsInsideTheDocumentRootAreRejected(): void
    {
        self::assertFalse(PathGuard::isOutside($this->docroot, $this->docroot));
        self::assertFalse(PathGuard::isOutside($this->docroot . '/storage', $this->docroot));
        self::assertFalse(PathGuard::isOutside($this->docroot . '/assets/uploads/deeper', $this->docroot));
        self::assertFalse(PathGuard::isOutside($this->base . '/uvs-private/../public_html/storage', $this->docroot));
        // A prefix that merely shares characters with the root is not inside it.
        self::assertTrue(PathGuard::isOutside($this->base . '/public_html_private', $this->docroot));
    }

    public function testSymlinkIntoTheDocumentRootIsRejected(): void
    {
        symlink($this->docroot . '/assets', $this->base . '/innocent-looking');
        self::assertFalse(PathGuard::isOutside($this->base . '/innocent-looking', $this->docroot));
        self::assertFalse(PathGuard::isOutside($this->base . '/innocent-looking/not-yet-created', $this->docroot));
        // A symlinked document root is resolved as well.
        symlink($this->docroot, $this->base . '/www');
        self::assertFalse(PathGuard::isOutside($this->docroot . '/x', $this->base . '/www'));
    }

    public function testUnresolvablePathsFailClosed(): void
    {
        self::assertFalse(PathGuard::isOutside('relative/storage', $this->docroot));
        self::assertFalse(PathGuard::isOutside('', $this->docroot));
        self::assertFalse(PathGuard::isOutside($this->base . "/uvs-private/a\0b", $this->docroot));
        self::assertFalse(PathGuard::isOutside($this->base . '/not-yet/../public_html', $this->docroot));
    }

    public function testConfigurationRejectsEveryPrivatePathInsideTheDocumentRoot(): void
    {
        $values = [
            'env' => 'production', 'base_url' => 'https://compendium.example', 'app_key' => bin2hex(random_bytes(32)),
            'db' => ['name' => 'db', 'user' => 'u', 'password' => 'p'],
            'storage_path' => $this->base . '/uvs-private/storage',
            'log_path' => $this->base . '/uvs-private/logs/app.log',
            'session' => ['save_path' => $this->base . '/uvs-private/sessions'],
        ];
        $configFile = $this->base . '/uvs-private/config.php';
        touch($configFile);
        $good = new Config($values, $configFile, $this->docroot);
        self::assertTrue($good->isUsable(), implode(' ', $good->problems()));

        foreach (['storage_path', 'log_path', 'session.save_path'] as $key) {
            $bad = $values;
            $inside = $this->docroot . '/assets/' . str_replace('.', '-', $key);
            if ($key === 'session.save_path') {
                $bad['session']['save_path'] = $inside;
            } else {
                $bad[$key] = $inside;
            }
            $config = new Config($bad, $configFile, $this->docroot);
            self::assertFalse($config->isUsable(), $key . ' inside the document root must be refused');
            self::assertStringContainsString($key, implode(' ', $config->problems()));
        }

        // The configuration file itself must not be web-reachable.
        $exposed = $this->docroot . '/config.php';
        touch($exposed);
        $config = new Config($values, $exposed, $this->docroot);
        self::assertFalse($config->isUsable());
        self::assertStringContainsString('configuration file', implode(' ', $config->problems()));

        // A symlink that points storage back into the document root is caught.
        symlink($this->docroot . '/assets', $this->base . '/uvs-private/linked');
        $linked = $values;
        $linked['storage_path'] = $this->base . '/uvs-private/linked/storage';
        self::assertFalse((new Config($linked, $configFile, $this->docroot))->isUsable());

        // Development configuration is held to the same rule.
        $development = $values;
        $development['env'] = 'development';
        $development['storage_path'] = $this->docroot . '/storage';
        self::assertFalse((new Config($development, $configFile, $this->docroot))->isUsable());
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
