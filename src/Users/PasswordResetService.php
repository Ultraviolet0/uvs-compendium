<?php

declare(strict_types=1);

namespace Uvs\Users;

use Uvs\Auth\PasswordHasher;
use Uvs\Auth\PasswordPolicy;
use Uvs\Database;
use Uvs\Mail\Mailer;
use Uvs\Support\ValidationException;

/**
 * Password reset with random, single-use, expiring tokens stored only as hashes.
 */
final class PasswordResetService
{
    public const TTL = 3600;

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly Mailer $mailer,
        private readonly PasswordPolicy $policy,
    ) {
    }

    /**
     * Starts a reset if the address belongs to an eligible account. The caller
     * always shows the same response, whether or not anything was sent.
     */
    public function request(string $email, string $resetBaseUrl): void
    {
        if (!EmailAddress::isValid($email)) {
            return;
        }
        $user = $this->users->findByEmail($email);
        if ($user === null || !in_array($user['status'], ['pending', 'active'], true) || !$this->mailer->isEnabled()) {
            return;
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = time();
        $issued = $this->db->transaction(function () use ($user, $token, $now): bool {
            // Lock the account and confirm the address is still its own, so a
            // token cannot be issued to an address that was just changed away.
            $current = $this->db->value('SELECT email_key FROM users WHERE id = :id FOR UPDATE', ['id' => (int) $user['id']]);
            if (!is_string($current) || !hash_equals($current, (string) $user['email_key'])) {
                return false;
            }
            $this->db->execute("DELETE FROM account_tokens WHERE user_id = :id AND purpose = 'password_reset'", ['id' => (int) $user['id']]);
            $this->db->execute(
                "INSERT INTO account_tokens (user_id, purpose, token_hash, created_at, expires_at)
                 VALUES (:id, 'password_reset', :hash, :created, :expires)",
                [
                    'id' => (int) $user['id'],
                    'hash' => self::hash($token),
                    'created' => gmdate('Y-m-d H:i:s', $now),
                    'expires' => gmdate('Y-m-d H:i:s', $now + self::TTL),
                ],
            );
            return true;
        });
        if (!$issued) {
            return;
        }
        $link = $resetBaseUrl . '?token=' . rawurlencode($token);
        $this->mailer->send((string) $user['email'], "Reset your UV's Compendium password",
            "Hello {$user['username']},\n\nSomeone asked to reset the password for your UV's Compendium account.\n"
            . "If it was you, open this link within one hour:\n\n{$link}\n\n"
            . "If you did not ask for this, you can ignore this message; your password has not changed.\n");
    }

    /**
     * @return array<string, mixed>|null the account for a valid, unused, unexpired token
     */
    public function userForToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }
        $row = $this->db->one(
            "SELECT t.id AS token_id, u.* FROM account_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :hash AND t.purpose = 'password_reset' AND t.used_at IS NULL AND t.expires_at > :now",
            ['hash' => self::hash($token), 'now' => Database::now()],
        );
        if ($row === null || !in_array($row['status'], ['pending', 'active'], true)) {
            return null;
        }
        return $row;
    }

    /**
     * @throws ValidationException
     */
    public function complete(string $token, string $password, string $confirmation): string
    {
        $user = $this->userForToken($token) ?? throw new ValidationException(['token' => 'This reset link is invalid or has expired. Request a new one.']);
        if (!hash_equals($password, $confirmation)) {
            throw new ValidationException(['password_confirmation' => 'The passwords do not match.']);
        }
        $problems = $this->policy->validate($password, (string) $user['username'], (string) $user['email']);
        if ($problems !== []) {
            throw new ValidationException(['password' => $problems[0]]);
        }
        $this->db->transaction(function () use ($user, $password): void {
            $claimed = $this->db->execute('UPDATE account_tokens SET used_at = :now WHERE id = :id AND used_at IS NULL',
                ['now' => Database::now(), 'id' => (int) $user['token_id']]);
            if ($claimed !== 1) {
                throw new ValidationException(['token' => 'This reset link has already been used.']);
            }
            $this->users->updatePasswordHash((int) $user['id'], PasswordHasher::hash($password), true);
            $this->db->execute("DELETE FROM account_tokens WHERE user_id = :id AND purpose = 'password_reset'",
                ['id' => (int) $user['id']]);
        });
        return (string) $user['username'];
    }

    public function purgeExpired(): int
    {
        return $this->db->execute('DELETE FROM account_tokens WHERE expires_at < :now OR used_at IS NOT NULL',
            ['now' => Database::now()]);
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
