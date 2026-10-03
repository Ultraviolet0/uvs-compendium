<?php

declare(strict_types=1);

namespace Uvs\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Uvs\App;
use Uvs\Config;
use Uvs\Console\Migrator;
use Uvs\Database;

/**
 * Integration tests run only against the disposable test database: the
 * environment must be "test" and the database name must end in "_test".
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static ?App $app = null;
    protected App $application;
    protected Database $db;

    public static function setUpBeforeClass(): void
    {
        $config = Config::load(dirname(__DIR__, 3));
        if (!$config->isTest() || !preg_match('/_test$/', (string) $config->get('db.name')) || !$config->isUsable()) {
            self::markTestSkipped('Integration tests need UVS_ENV=test and a *_test database (run inside the web-test container).');
        }
        $app = new App(dirname(__DIR__, 3), $config);
        App::setInstance($app);
        $migrator = new Migrator($app->db(), $app->root . '/migrations');
        $migrator->dropAllTables();
        $migrator->migrate();
        self::$app = $app;
    }

    protected function setUp(): void
    {
        if (self::$app === null) {
            self::markTestSkipped('No test database.');
        }
        // A fresh application per test so cached services (settings, auth) never leak between tests.
        $this->application = new App(self::$app->root, self::$app->config);
        $this->application->setDatabase(self::$app->db());
        App::setInstance($this->application);
        $this->db = $this->application->db();
        foreach (['audit_events', 'rate_limits', 'settings', 'account_tokens', 'mfa_recovery_codes', 'user_characters', 'user_profiles', 'media', 'guide_revisions', 'guides', 'users'] as $table) {
            $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
            $this->db->pdo()->exec("DELETE FROM {$table}");
            $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function user(string $username, string $status = 'active', string $role = 'member'): array
    {
        $id = $this->application->users()->create($username, strtolower($username) . '@example.test',
            \Uvs\Auth\PasswordHasher::hash('a sufficiently long passphrase'), $status, $role);
        return (array) $this->application->users()->find($id);
    }
}
