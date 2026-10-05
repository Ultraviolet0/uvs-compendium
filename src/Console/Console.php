<?php

declare(strict_types=1);

namespace Uvs\Console;

use RuntimeException;
use Throwable;
use Uvs\App;
use Uvs\Auth\PasswordHasher;
use Uvs\Support\ValidationException;

/**
 * Operator command line. It only runs under the CLI SAPI, so none of these
 * operations (bootstrap, migrations, recovery) is reachable over HTTP.
 */
final class Console
{
    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    public function __construct(private readonly App $app)
    {
        $this->out = STDOUT;
        $this->err = STDERR;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        if (PHP_SAPI !== 'cli') {
            return 1;
        }
        [$command, $args, $options] = self::parse(array_slice($argv, 1));
        try {
            return match ($command) {
                'migrate' => $this->migrate(),
                'migrate:status' => $this->migrateStatus(),
                'db:reset-test' => $this->resetTestDatabase(),
                'admin:create' => $this->createAdmin($options),
                'user:set-role' => $this->setRole($args, $options),
                'user:password' => $this->setPassword($args, $options),
                'user:mfa-disable' => $this->disableMfa($args),
                'user:delete' => $this->deleteUser($args, $options),
                'media:cleanup' => $this->cleanupMedia($options),
                'maintenance:prune' => $this->prune(),
                'rate-limits:clear' => $this->clearRateLimits(),
                'config:check' => $this->checkConfig(),
                default => $this->help(),
            };
        } catch (ValidationException $error) {
            foreach ($error->errors as $message) {
                $this->error($message);
            }
            return 1;
        } catch (Throwable $error) {
            $this->error('Command failed: ' . $error->getMessage());
            return 1;
        }
    }

    /**
     * @param list<string> $arguments
     * @return array{0: string, 1: list<string>, 2: array<string, string|bool>}
     */
    private static function parse(array $arguments): array
    {
        $command = array_shift($arguments) ?? 'help';
        $args = [];
        $options = [];
        foreach ($arguments as $argument) {
            if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $argument, $match)) {
                $options[$match[1]] = $match[2] ?? true;
            } else {
                $args[] = $argument;
            }
        }
        return [$command, $args, $options];
    }

    private function help(): int
    {
        $this->line(<<<'TEXT'
UV's Compendium operator console

  migrate                              Apply pending database migrations
  migrate:status                       List migrations and whether they are applied
  db:reset-test                        Drop and rebuild the disposable test database (UVS_ENV=test only)
  admin:create --username=NAME --email=ADDRESS [--password-stdin]
                                       Create the first administrator (refused once one exists)
  user:set-role USERNAME member|admin  Change a role from the command line (recovery)
  user:password USERNAME [--password-stdin]
                                       Set a new password and sign out all sessions (recovery)
  user:mfa-disable USERNAME            Remove two-factor authentication (lockout recovery)
  user:delete USERNAME --confirm=USERNAME
                                       Permanently delete an account, its profile, and its media
  media:cleanup [--dry-run]            Delete abandoned uploads and their files
  maintenance:prune                    Remove expired rate-limit and token rows
  rate-limits:clear                    Reset all throttling counters (for example after a false lockout)
  config:check                         Report configuration problems without printing secrets
TEXT);
        return 0;
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->app->db(), $this->app->root . '/migrations');
    }

    private function migrate(): int
    {
        $ran = $this->migrator()->migrate(fn (string $message) => $this->line($message));
        $this->line($ran === [] ? 'Database is up to date.' : 'Applied ' . count($ran) . ' migration(s).');
        return 0;
    }

    private function migrateStatus(): int
    {
        $pending = 0;
        foreach ($this->migrator()->status() as $row) {
            $this->line(sprintf('%-12s %-40s %s', $row['state'], $row['version'], $row['applied_at'] ?? ''));
            $pending += $row['state'] === 'applied' ? 0 : 1;
        }
        return $pending === 0 ? 0 : 2;
    }

    private function resetTestDatabase(): int
    {
        $name = (string) $this->app->config->get('db.name');
        if (!$this->app->config->isTest() || !preg_match('/_test$/', $name)) {
            throw new RuntimeException('Refusing to reset: UVS_ENV must be "test" and the database name must end in "_test".');
        }
        $migrator = $this->migrator();
        $migrator->dropAllTables();
        $migrator->migrate();
        $media = $this->app->storagePath('media');
        if (is_dir($media)) {
            self::removeTree($media);
        }
        $mail = $this->app->storagePath('mail');
        if (is_dir($mail)) {
            self::removeTree($mail);
        }
        $this->line("Test database {$name} rebuilt.");
        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function createAdmin(array $options): int
    {
        $username = is_string($options['username'] ?? null) ? trim($options['username']) : $this->prompt('Administrator username: ');
        $email = is_string($options['email'] ?? null) ? trim($options['email']) : $this->prompt('Administrator email: ');
        $password = $this->readPassword($options);
        $id = $this->app->accounts()->bootstrapAdmin($username, $email, $password);
        $this->line("Administrator {$username} created (id {$id}). Sign in at /account/login/ and enable two-factor authentication.");
        return 0;
    }

    /**
     * @param list<string> $args
     * @param array<string, string|bool> $options
     */
    private function setRole(array $args, array $options): int
    {
        [$username, $role] = $args + [null, null];
        if (!is_string($username) || !in_array($role, ['member', 'admin'], true)) {
            throw new RuntimeException('Usage: user:set-role USERNAME member|admin');
        }
        $user = $this->app->users()->findByUsername($username) ?? throw new RuntimeException('No such user.');
        // Serialized with administrator changes made on the web, so the last active admin cannot be removed.
        $this->app->accounts()->withAdminInvariant(function () use ($user, $role): void {
            $updated = $this->app->db()->execute(
                'UPDATE users SET role = :role, auth_epoch = auth_epoch + 1, updated_at = UTC_TIMESTAMP() WHERE id = :id AND role = :previous',
                ['role' => $role, 'id' => (int) $user['id'], 'previous' => $user['role']],
            );
            if ($updated !== 1 && $user['role'] !== $role) {
                throw new RuntimeException('The account changed while this command was running; run it again.');
            }
            $this->app->audit()->record(null, 'user.role_changed', 'user', (int) $user['id'], (string) $user['username'],
                ['from' => $user['role'], 'to' => $role, 'via' => 'cli']);
        });
        $this->line("{$user['username']} is now {$role}.");
        return 0;
    }

    /**
     * @param list<string> $args
     * @param array<string, string|bool> $options
     */
    private function setPassword(array $args, array $options): int
    {
        $user = $this->app->users()->findByUsername((string) ($args[0] ?? '')) ?? throw new RuntimeException('No such user.');
        $password = $this->readPassword($options);
        $problems = $this->app->passwordPolicy()->validate($password, (string) $user['username'], (string) $user['email']);
        if ($problems !== []) {
            throw new ValidationException(['password' => $problems[0]]);
        }
        $this->app->users()->updatePasswordHash((int) $user['id'], PasswordHasher::hash($password), true);
        $this->app->audit()->record(null, 'user.password_reset_by_cli', 'user', (int) $user['id'], (string) $user['username']);
        $this->line("Password updated for {$user['username']}; existing sessions were signed out.");
        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function disableMfa(array $args): int
    {
        $user = $this->app->users()->findByUsername((string) ($args[0] ?? '')) ?? throw new RuntimeException('No such user.');
        $this->app->mfa()->disable((int) $user['id']);
        $this->app->audit()->record(null, 'user.mfa_disabled_by_cli', 'user', (int) $user['id'], (string) $user['username']);
        $this->line("Two-factor authentication removed for {$user['username']}.");
        return 0;
    }

    /**
     * @param list<string> $args
     * @param array<string, string|bool> $options
     */
    private function deleteUser(array $args, array $options): int
    {
        $username = (string) ($args[0] ?? '');
        $user = $this->app->users()->findByUsername($username) ?? throw new RuntimeException('No such user.');
        if (($options['confirm'] ?? null) !== $user['username']) {
            throw new RuntimeException("Repeat the exact username with --confirm={$user['username']} to delete this account.");
        }
        $media = $this->app->accounts()->withAdminInvariant(function () use ($user): array {
            $rows = $this->app->db()->all('SELECT * FROM media WHERE owner_id = :id', ['id' => (int) $user['id']]);
            $this->app->db()->execute('DELETE FROM users WHERE id = :id', ['id' => (int) $user['id']]);
            $this->app->audit()->record(null, 'user.deleted', 'user', (int) $user['id'], (string) $user['username']);
            return $rows;
        });
        // Files are removed only after the account deletion has committed.
        $this->app->media()->deleteRows($media);
        $this->line("Deleted {$user['username']}. Their guides remain, credited to a former member, unless removed separately.");
        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function cleanupMedia(array $options): int
    {
        $dryRun = isset($options['dry-run']);
        $removed = $this->app->media()->cleanupOrphans($dryRun);
        $this->line(($dryRun ? 'Would remove ' : 'Removed ') . count($removed) . ' abandoned upload(s).');
        foreach ($removed as $publicId) {
            $this->line('  ' . $publicId);
        }
        return 0;
    }

    private function prune(): int
    {
        $limits = $this->app->rateLimiter()->purgeExpired();
        $tokens = $this->app->passwordResets()->purgeExpired();
        $this->line("Removed {$limits} expired rate-limit row(s) and {$tokens} expired or used token(s).");
        return 0;
    }

    private function clearRateLimits(): int
    {
        $removed = $this->app->db()->execute('DELETE FROM rate_limits');
        $this->line("Cleared {$removed} rate-limit counter(s).");
        return 0;
    }

    private function checkConfig(): int
    {
        $config = $this->app->config;
        $this->line('Environment: ' . $config->env());
        $this->line('Configuration source: ' . ($config->source() === null ? 'none' : ($config->source() === 'environment' ? 'environment variables' : 'private file')));
        foreach ($config->problems() as $problem) {
            $this->error('Problem: ' . $problem);
        }
        if (!$config->isUsable()) {
            return 1;
        }
        $this->line('Database: ' . ($this->app->db()->value('SELECT 1') === 1 ? 'reachable' : 'unexpected response'));
        $storage = $this->app->storagePath();
        $this->line('Storage: ' . (is_dir($storage) && is_writable($storage) ? 'writable' : 'NOT writable'));
        $this->line('Turnstile: ' . $this->app->turnstile()->status());
        $this->line('Mail transport: ' . $this->app->mailer()->transport());
        $this->line('Password hashing: ' . (PasswordHasher::usesBcrypt() ? 'bcrypt (72-byte limit enforced)' : 'Argon2id'));
        $this->line('Image formats: ' . implode(', ', array_keys(array_filter([
            'JPEG' => function_exists('imagecreatefromjpeg'),
            'PNG' => function_exists('imagecreatefrompng'),
            'WebP' => function_exists('imagewebp'),
            'GIF' => function_exists('imagecreatefromgif'),
        ]))));
        return 0;
    }

    /**
     * Reads a password without echoing it and without placing it in shell history.
     *
     * @param array<string, string|bool> $options
     */
    private function readPassword(array $options): string
    {
        if (isset($options['password'])) {
            throw new RuntimeException('Passwords are not accepted as command-line arguments. Use the prompt or --password-stdin.');
        }
        if (isset($options['password-stdin'])) {
            $line = fgets(STDIN);
            return rtrim($line === false ? '' : $line, "\r\n");
        }
        $interactive = function_exists('posix_isatty') && posix_isatty(STDIN);
        if (!$interactive) {
            throw new RuntimeException('No terminal available; pipe the password with --password-stdin.');
        }
        $first = $this->hiddenPrompt('Password: ');
        $second = $this->hiddenPrompt('Repeat password: ');
        if (!hash_equals($first, $second)) {
            throw new RuntimeException('The passwords did not match.');
        }
        return $first;
    }

    private function hiddenPrompt(string $label): string
    {
        fwrite($this->out, $label);
        shell_exec('stty -echo');
        try {
            $line = fgets(STDIN);
        } finally {
            shell_exec('stty echo');
            fwrite($this->out, "\n");
        }
        return rtrim($line === false ? '' : $line, "\r\n");
    }

    private function prompt(string $label): string
    {
        fwrite($this->out, $label);
        $line = fgets(STDIN);
        return trim($line === false ? '' : $line);
    }

    private static function removeTree(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }

    private function line(string $message): void
    {
        fwrite($this->out, $message . "\n");
    }

    private function error(string $message): void
    {
        fwrite($this->err, $message . "\n");
    }
}
