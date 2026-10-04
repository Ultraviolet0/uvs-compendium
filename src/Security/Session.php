<?php

declare(strict_types=1);

namespace Uvs\Security;

use Uvs\Config;

/**
 * Hardened wrapper around native PHP sessions.
 *
 * Anonymous visitors on public pages never receive a session cookie: a session is
 * only created by pages that need one (forms with CSRF tokens) and only resumed
 * elsewhere when the browser already presents a cookie.
 */
final class Session
{
    private bool $started = false;

    public function __construct(private readonly Config $config, private readonly string $storagePath)
    {
    }

    public function cookieName(): string
    {
        $name = (string) $this->config->get('session.name', 'uvs_session');
        return $this->secureCookies() ? '__Host-' . $name : $name;
    }

    public function secureCookies(): bool
    {
        if ($this->config->isProduction()) {
            return true;
        }
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public function isActive(): bool
    {
        return $this->started && session_status() === PHP_SESSION_ACTIVE;
    }

    public function hasCookie(): bool
    {
        $value = $_COOKIE[$this->cookieName()] ?? null;
        return is_string($value) && preg_match('/^[A-Za-z0-9,-]{22,256}$/', $value) === 1;
    }

    /** Resumes an existing session without creating a new one. */
    public function resumeIfPresent(): bool
    {
        if ($this->isActive()) {
            return true;
        }
        if (!$this->hasCookie()) {
            return false;
        }
        $this->start();
        return true;
    }

    public function start(): void
    {
        if ($this->isActive()) {
            return;
        }
        if (PHP_SAPI === 'cli' && !defined('UVS_TESTING_SESSIONS')) {
            $this->started = true;
            $_SESSION ??= [];
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $this->secureCookies() ? '1' : '0');
        ini_set('session.gc_maxlifetime', (string) max(
            (int) $this->config->get('session.idle_timeout'),
            (int) $this->config->get('session.absolute_timeout'),
        ));
        $savePath = $this->config->get('session.save_path');
        if (!is_string($savePath) && $this->storagePath !== '') {
            $savePath = $this->storagePath . '/sessions';
        }
        if (is_string($savePath) && $savePath !== '') {
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0700, true);
            }
            if (is_dir($savePath) && is_writable($savePath)) {
                session_save_path($savePath);
                // A private save path is not cleaned by the operating system.
                ini_set('session.gc_probability', '1');
                ini_set('session.gc_divisor', '200');
            }
        }
        session_name($this->cookieName());
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secureCookies(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_cache_limiter('');
        session_start();
        $this->started = true;
        $this->enforceLifetime();
    }

    private function enforceLifetime(): void
    {
        $now = time();
        $meta = $_SESSION['_meta'] ?? null;
        if (!is_array($meta)) {
            $_SESSION['_meta'] = ['created' => $now, 'last' => $now];
            return;
        }
        $idle = (int) $this->config->get('session.idle_timeout');
        if (!empty($_SESSION['auth']['admin'])) {
            $idle = min($idle, (int) $this->config->get('session.admin_idle_timeout'));
        }
        $absolute = (int) $this->config->get('session.absolute_timeout');
        if ($now - (int) ($meta['last'] ?? 0) > $idle || $now - (int) ($meta['created'] ?? 0) > $absolute) {
            $expiredUser = isset($_SESSION['auth']['user_id']);
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_meta'] = ['created' => $now, 'last' => $now];
            if ($expiredUser) {
                $_SESSION['_flash'][] = ['type' => 'info', 'message' => 'Your session expired. Please sign in again.'];
            }
            return;
        }
        $_SESSION['_meta']['last'] = $now;
    }

    /** Issues a new session identifier and discards the old one (fixation defence). */
    public function regenerate(): void
    {
        $this->start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_meta'] = ['created' => time(), 'last' => time()];
    }

    public function destroy(): void
    {
        if (!$this->isActive()) {
            return;
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie($this->cookieName(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
        $this->started = false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->resumeIfPresent() ? ($_SESSION[$key] ?? $default) : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        if ($this->resumeIfPresent()) {
            unset($_SESSION[$key]);
        }
    }

    public function flash(string $type, string $message): void
    {
        $this->start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return list<array{type: string, message: string}>
     */
    public function pullFlashes(): array
    {
        if (!$this->isActive()) {
            return [];
        }
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? array_values($messages) : [];
    }
}
