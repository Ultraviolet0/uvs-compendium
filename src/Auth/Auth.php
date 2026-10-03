<?php

declare(strict_types=1);

namespace Uvs\Auth;

use Uvs\App;
use Uvs\Database;
use Uvs\Security\Csrf;
use Uvs\Users\UserRepository;

/**
 * Authentication state for the current request.
 *
 * The session stores only the user id and the account's auth epoch. Each request
 * reloads the account, so status, role, and epoch changes (suspension, password
 * change, role change) take effect immediately and revoke older sessions.
 */
final class Auth
{
    public const MFA_PENDING_TTL = 300;

    /** @var array<string, mixed>|null */
    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $session = $this->app->session();
        if (!$session->resumeIfPresent()) {
            return null;
        }
        $auth = $session->get('auth');
        if (!is_array($auth) || !isset($auth['user_id'], $auth['epoch'])) {
            return null;
        }
        $user = (new UserRepository($this->app->db()))->findWithProfile((int) $auth['user_id']);
        if ($user === null || (int) $user['auth_epoch'] !== (int) $auth['epoch']
            || !in_array($user['status'], ['pending', 'active'], true)) {
            $this->endSession('Your session has ended. Please sign in again.');
            return null;
        }
        // Privilege changes (approval, role change) rotate the session identifier and
        // keep the shorter administrator idle timeout in step with the current role.
        $privilege = $user['status'] . ':' . $user['role'];
        if (($auth['privilege'] ?? null) !== $privilege) {
            if (isset($auth['privilege'])) {
                $session->regenerate();
                (new Csrf($session))->rotate();
            }
            $auth['privilege'] = $privilege;
            $auth['admin'] = $user['role'] === 'admin';
            $session->set('auth', $auth);
        }
        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user === null ? null : (int) $user['id'];
    }

    /**
     * Verifies credentials with throttling. Does not establish the session.
     *
     * @return array{result: string, user?: array<string, mixed>}
     *   result is one of ok, mfa, invalid, throttled, suspended, rejected
     */
    public function attempt(string $identifier, string $password, string $ip): array
    {
        $limiter = $this->app->rateLimiter();
        $identifier = trim($identifier);
        $ipAllowed = $limiter->hit('login.ip', $ip);
        $accountAllowed = $limiter->hit('login.account', $identifier);
        if (!$ipAllowed || !$accountAllowed) {
            PasswordHasher::burn($password);
            return ['result' => 'throttled'];
        }
        $users = new UserRepository($this->app->db());
        $user = $users->findByLogin($identifier);
        if ($user === null || strlen($password) > 1024) {
            PasswordHasher::burn($password);
            return ['result' => 'invalid'];
        }
        if (!PasswordHasher::verify($password, (string) $user['password_hash'])) {
            return ['result' => 'invalid'];
        }
        if (PasswordHasher::needsRehash((string) $user['password_hash'])) {
            $users->updatePasswordHash((int) $user['id'], PasswordHasher::hash($password), false);
        }
        $limiter->clear('login.account', $identifier);
        if ($user['status'] === 'suspended' || $user['status'] === 'rejected') {
            return ['result' => (string) $user['status'], 'user' => $user];
        }
        if ($user['mfa_enabled_at'] !== null) {
            return ['result' => 'mfa', 'user' => $user];
        }
        return ['result' => 'ok', 'user' => $user];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function beginMfa(array $user): void
    {
        $session = $this->app->session();
        $session->regenerate();
        $session->remove('auth');
        $session->set('mfa_pending', [
            'user_id' => (int) $user['id'],
            'epoch' => (int) $user['auth_epoch'],
            'expires' => time() + self::MFA_PENDING_TTL,
        ]);
    }

    /**
     * @return array<string, mixed>|null the user awaiting a second factor
     */
    public function pendingMfaUser(): ?array
    {
        $session = $this->app->session();
        if (!$session->resumeIfPresent()) {
            return null;
        }
        $pending = $session->get('mfa_pending');
        if (!is_array($pending) || (int) ($pending['expires'] ?? 0) < time()) {
            $session->remove('mfa_pending');
            return null;
        }
        $user = (new UserRepository($this->app->db()))->find((int) $pending['user_id']);
        if ($user === null || (int) $user['auth_epoch'] !== (int) $pending['epoch'] || $user['mfa_enabled_at'] === null
            || !in_array($user['status'], ['pending', 'active'], true)) {
            $session->remove('mfa_pending');
            return null;
        }
        return $user;
    }

    /**
     * Establishes an authenticated session with a fresh identifier.
     *
     * @param array<string, mixed> $user
     */
    public function completeLogin(array $user): void
    {
        $session = $this->app->session();
        $session->regenerate();
        $session->remove('mfa_pending');
        $session->set('auth', [
            'user_id' => (int) $user['id'],
            'epoch' => (int) $user['auth_epoch'],
            'admin' => $user['role'] === 'admin',
            'privilege' => $user['status'] . ':' . $user['role'],
            'at' => time(),
        ]);
        (new Csrf($session))->rotate();
        $this->app->db()->execute('UPDATE users SET last_login_at = :now WHERE id = :id',
            ['now' => Database::now(), 'id' => (int) $user['id']]);
        $this->user = null;
        $this->loaded = false;
    }

    /**
     * Re-binds the current session after this user's epoch changed (for example
     * after their own password change), so only other sessions are revoked.
     */
    public function rebindCurrentSession(): void
    {
        $auth = $this->app->session()->get('auth');
        if (!is_array($auth)) {
            return;
        }
        $user = (new UserRepository($this->app->db()))->find((int) $auth['user_id']);
        if ($user === null) {
            return;
        }
        $this->completeLogin($user);
    }

    public function logout(): void
    {
        $this->app->session()->destroy();
        $this->user = null;
        $this->loaded = true;
    }

    public function forget(): void
    {
        $this->user = null;
        $this->loaded = false;
    }

    private function endSession(string $message): void
    {
        $session = $this->app->session();
        $session->remove('auth');
        $session->regenerate();
        $session->flash('info', $message);
    }
}
