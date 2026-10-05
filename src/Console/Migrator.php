<?php

declare(strict_types=1);

namespace Uvs\Console;

use RuntimeException;
use Uvs\Database;

/**
 * Ordered, recorded, repeatable schema migrations.
 *
 * Each file in migrations/ is named NNNN_description.php and returns a list of
 * SQL strings or callables. Applied versions are recorded with a checksum; an
 * applied file that later changes is reported instead of silently re-run. A
 * database-level advisory lock prevents concurrent runs.
 */
final class Migrator
{
    private const LOCK = 'uvs_compendium_migrations';

    public function __construct(private readonly Database $db, private readonly string $directory)
    {
    }

    public function db(): Database
    {
        return $this->db;
    }

    /**
     * @return array<string, string> version => absolute file path, in order
     */
    public function available(): array
    {
        $files = glob($this->directory . '/[0-9][0-9][0-9][0-9]_*.php') ?: [];
        sort($files, SORT_STRING);
        $versions = [];
        foreach ($files as $file) {
            $versions[basename($file, '.php')] = $file;
        }
        return $versions;
    }

    /**
     * @return array<string, array{checksum: string, applied_at: string}>
     */
    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->db->all('SELECT version, checksum, applied_at FROM schema_migrations ORDER BY version');
        $applied = [];
        foreach ($rows as $row) {
            $applied[(string) $row['version']] = ['checksum' => (string) $row['checksum'], 'applied_at' => (string) $row['applied_at']];
        }
        return $applied;
    }

    /**
     * @return list<array{version: string, state: string, applied_at: ?string}>
     */
    public function status(): array
    {
        $applied = $this->applied();
        $status = [];
        foreach ($this->available() as $version => $file) {
            $state = 'pending';
            if (isset($applied[$version])) {
                $state = hash_equals($applied[$version]['checksum'], $this->checksum($file)) ? 'applied' : 'changed';
            }
            $status[] = ['version' => $version, 'state' => $state, 'applied_at' => $applied[$version]['applied_at'] ?? null];
        }
        foreach (array_diff_key($applied, $this->available()) as $version => $row) {
            $status[] = ['version' => $version, 'state' => 'missing-file', 'applied_at' => $row['applied_at']];
        }
        return $status;
    }

    /**
     * Applies every pending migration in order.
     *
     * @param null|callable(string): void $report
     * @return list<string> applied versions
     */
    public function migrate(?callable $report = null): array
    {
        $this->ensureTable();
        if ((int) $this->db->value('SELECT GET_LOCK(:name, 30)', ['name' => self::LOCK]) !== 1) {
            throw new RuntimeException('Another migration run holds the lock.');
        }
        try {
            $applied = $this->applied();
            foreach ($applied as $version => $row) {
                $file = $this->available()[$version] ?? null;
                if ($file !== null && !hash_equals($row['checksum'], $this->checksum($file))) {
                    throw new RuntimeException("Applied migration {$version} has changed; add a new migration instead.");
                }
            }
            $ran = [];
            foreach ($this->available() as $version => $file) {
                if (isset($applied[$version])) {
                    continue;
                }
                $report && $report("Applying {$version}");
                $steps = (static fn (string $path): mixed => require $path)($file);
                if (!is_array($steps)) {
                    throw new RuntimeException("Migration {$version} must return a list of steps.");
                }
                foreach ($steps as $step) {
                    if (is_string($step)) {
                        $this->db->pdo()->exec($step);
                    } elseif (is_callable($step)) {
                        $step($this);
                    } else {
                        throw new RuntimeException("Migration {$version} contains an invalid step.");
                    }
                }
                $this->db->execute(
                    'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (:version, :checksum, :applied)',
                    ['version' => $version, 'checksum' => $this->checksum($file), 'applied' => Database::now()],
                );
                $ran[] = $version;
            }
            return $ran;
        } finally {
            $this->db->value('SELECT RELEASE_LOCK(:name)', ['name' => self::LOCK]);
        }
    }

    public function constraintExists(string $table, string $constraint): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :name',
            ['table' => $table, 'name' => $constraint],
        ) > 0;
    }

    public function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :name',
            ['table' => $table, 'name' => $column],
        ) > 0;
    }

    /**
     * Drops every table in the current database. Callers must verify the target
     * is a disposable test database first.
     */
    public function dropAllTables(): void
    {
        $tables = $this->db->all('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $row) {
                $name = (string) $row['TABLE_NAME'];
                if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                    throw new RuntimeException('Unexpected table name.');
                }
                $this->db->pdo()->exec('DROP TABLE `' . $name . '`');
            }
        } finally {
            $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function ensureTable(): void
    {
        $this->db->pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function checksum(string $file): string
    {
        // Normalise line endings so a Windows checkout produces the same checksum.
        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file)));
    }
}
