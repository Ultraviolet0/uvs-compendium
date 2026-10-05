<?php

declare(strict_types=1);

namespace Uvs;

use RuntimeException;
use Uvs\Admin\AuditLog;
use Uvs\Admin\Settings;
use Uvs\Auth\Auth;
use Uvs\Auth\Gate;
use Uvs\Auth\Mfa;
use Uvs\Auth\PasswordPolicy;
use Uvs\Guides\GuideRepository;
use Uvs\Guides\GuideWorkflow;
use Uvs\Guides\MarkdownRenderer;
use Uvs\Media\MediaService;
use Uvs\Security\SecretBox;
use Uvs\Users\AccountService;
use Uvs\Users\PasswordResetService;
use Uvs\Users\UserRepository;
use Uvs\Mail\Mailer;
use Uvs\Security\RateLimiter;
use Uvs\Security\Session;
use Uvs\Security\Turnstile\Turnstile;

/**
 * Lazily constructed application services. There is deliberately no generic
 * container: each service has one explicit factory below.
 */
final class App
{
    private static ?self $instance = null;

    private ?Database $database = null;
    private ?Session $session = null;
    private ?Auth $auth = null;
    private ?Settings $settings = null;
    private ?Logger $logger = null;
    private ?AuditLog $audit = null;
    private ?RateLimiter $rateLimiter = null;
    private ?Mailer $mailer = null;
    private ?Turnstile $turnstile = null;

    public function __construct(
        public readonly string $root,
        public readonly Config $config,
    ) {
    }

    public static function boot(?string $root = null): self
    {
        if (self::$instance === null) {
            $root ??= dirname(__DIR__);
            date_default_timezone_set('UTC');
            self::$instance = new self($root, Config::load($root));
        }
        return self::$instance;
    }

    public static function instance(): self
    {
        return self::$instance ?? self::boot();
    }

    /** Replaces the shared instance; intended for tests and the CLI. */
    public static function setInstance(?self $app): void
    {
        self::$instance = $app;
    }

    public function db(): Database
    {
        if (!$this->config->isUsable()) {
            throw new RuntimeException('Application configuration is incomplete.');
        }
        return $this->database ??= Database::connect($this->config);
    }

    public function setDatabase(Database $database): void
    {
        $this->database = $database;
    }

    public function storagePath(string $relative = ''): string
    {
        $base = rtrim((string) $this->config->get('storage_path'), '/');
        return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
    }

    public function logger(): Logger
    {
        if ($this->logger === null) {
            $path = $this->config->get('log_path');
            if (!is_string($path) && is_string($this->config->get('storage_path'))) {
                $path = $this->storagePath('logs');
            }
            $this->logger = new Logger(is_string($path) ? $path : null);
        }
        return $this->logger;
    }

    public function session(): Session
    {
        return $this->session ??= new Session($this->config, $this->storagePath());
    }

    public function auth(): Auth
    {
        return $this->auth ??= new Auth($this);
    }

    public function gate(): Gate
    {
        return new Gate();
    }

    public function settings(): Settings
    {
        return $this->settings ??= new Settings($this->db(), $this->config);
    }

    public function audit(): AuditLog
    {
        return $this->audit ??= new AuditLog($this->db());
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->rateLimiter ??= new RateLimiter($this->db(), $this->appKey());
    }

    public function mailer(): Mailer
    {
        return $this->mailer ??= new Mailer($this->config, $this->storagePath('mail'), $this->logger());
    }

    public function turnstile(): Turnstile
    {
        return $this->turnstile ??= Turnstile::fromConfig($this->config, $this->logger());
    }

    public function setTurnstile(Turnstile $turnstile): void
    {
        $this->turnstile = $turnstile;
    }

    public function users(): UserRepository
    {
        return new UserRepository($this->db());
    }

    public function passwordPolicy(): PasswordPolicy
    {
        return new PasswordPolicy((int) $this->config->get('password.min_length', 10),
            (int) $this->config->get('password.max_length', 256));
    }

    public function accounts(): AccountService
    {
        return new AccountService($this->db(), $this->users(), $this->settings(), $this->audit(), $this->passwordPolicy());
    }

    public function passwordResets(): PasswordResetService
    {
        return new PasswordResetService($this->db(), $this->users(), $this->mailer(), $this->passwordPolicy());
    }

    public function mfa(): Mfa
    {
        return new Mfa($this->db(), new SecretBox($this->derivedKey('mfa-secret')), $this->derivedKey('mfa-recovery'));
    }

    public function guides(): GuideRepository
    {
        return new GuideRepository($this->db());
    }

    public function guideWorkflow(): GuideWorkflow
    {
        return new GuideWorkflow($this->db(), $this->guides(), $this->audit(), $this->settings(), $this->root,
            (int) $this->config->get('guides.max_body_length', 60000));
    }

    public function markdown(string $mediaBaseUrl): MarkdownRenderer
    {
        return new MarkdownRenderer($mediaBaseUrl);
    }

    public function media(): MediaService
    {
        return new MediaService($this->db(), $this->config, $this->settings(), $this->storagePath('media'), $this->logger());
    }

    public function appKey(): string
    {
        $key = $this->config->get('app_key');
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Application key is not configured.');
        }
        return $key;
    }

    /**
     * Derives an independent key for one purpose from the application key.
     */
    public function derivedKey(string $purpose): string
    {
        return hash_hkdf('sha256', $this->appKey(), 32, 'uvs-compendium:' . $purpose);
    }
}
