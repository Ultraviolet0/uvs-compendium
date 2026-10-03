<?php

declare(strict_types=1);

namespace Uvs;

/**
 * Application configuration.
 *
 * Production configuration is a PHP file that returns an array and lives outside
 * the deployed document root (see docs/configuration.md). Local development and
 * automated tests use environment variables supplied by compose.yaml instead.
 */
final class Config
{
    public const ENVIRONMENTS = ['production', 'development', 'test'];

    /** @var array<string, mixed> */
    private array $values;

    /** @var list<string> */
    private array $problems;

    private ?string $source;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values, ?string $source = null)
    {
        $this->values = self::merge(self::defaults(), $values);
        $this->source = $source;
        if (!in_array($this->values['env'], self::ENVIRONMENTS, true)) {
            $this->values['env'] = 'production';
        }
        $this->problems = $this->validate();
    }

    public static function load(string $appRoot): self
    {
        $explicit = getenv('UVS_CONFIG_FILE');
        $candidates = [];
        if (is_string($explicit) && $explicit !== '') {
            $candidates[] = $explicit;
        }
        // Default production location: a sibling of the deployed document root.
        $candidates[] = dirname($appRoot) . '/uvs-private/config.php';

        foreach ($candidates as $index => $file) {
            if (!is_file($file)) {
                if ($index === 0 && $file === $explicit) {
                    return new self(['env' => 'production'], null);
                }
                continue;
            }
            $values = (static fn (string $path): mixed => require $path)($file);
            if (!is_array($values)) {
                return new self(['env' => 'production'], null);
            }
            return new self($values, $file);
        }

        $fromEnvironment = self::fromEnvironment();
        return new self($fromEnvironment, $fromEnvironment === [] ? null : 'environment');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'env' => 'production',
            'base_url' => null,
            'app_key' => null,
            'db' => [
                'host' => 'localhost',
                'port' => 3306,
                'socket' => null,
                'name' => null,
                'user' => null,
                'password' => null,
            ],
            'storage_path' => null,
            'log_path' => null,
            'session' => [
                'name' => 'uvs_session',
                'idle_timeout' => 7200,
                'absolute_timeout' => 43200,
                'admin_idle_timeout' => 3600,
                'save_path' => null,
            ],
            'turnstile' => [
                'mode' => 'enabled',
                'site_key' => null,
                'secret_key' => null,
                'timeout' => 5,
            ],
            'mail' => [
                'transport' => 'disabled',
                'from_address' => null,
                'from_name' => "UV's Compendium",
                'smtp' => [
                    'host' => null,
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => null,
                    'password' => null,
                    'timeout' => 10,
                ],
            ],
            'trusted_proxies' => [],
            'client_ip_header' => null,
            'signup' => [
                'min_seconds' => 3,
                'max_age' => 7200,
            ],
            'password' => [
                'min_length' => 10,
                'max_length' => 256,
            ],
            'media' => [
                // Hard ceilings. Administrators may lower, but never raise, these in settings.
                'max_upload_bytes' => 10 * 1024 * 1024,
                'max_processed_bytes' => 2 * 1024 * 1024,
                'max_source_pixels' => 40_000_000,
                'max_guide_dimension' => 1600,
                'avatar_dimension' => 256,
                'max_quota_bytes' => 200 * 1024 * 1024,
                'max_images_per_guide' => 40,
                'orphan_grace_hours' => 24,
            ],
            'guides' => [
                'max_drafts' => 25,
                'max_body_length' => 60000,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fromEnvironment(): array
    {
        $env = static function (string $name): ?string {
            $value = getenv($name);
            return is_string($value) && $value !== '' ? $value : null;
        };
        if ($env('UVS_ENV') === null) {
            return [];
        }
        $values = [
            'env' => $env('UVS_ENV'),
            'base_url' => $env('UVS_BASE_URL'),
            'app_key' => $env('UVS_APP_KEY'),
            'db' => array_filter([
                'host' => $env('UVS_DB_HOST'),
                'port' => $env('UVS_DB_PORT') !== null ? (int) $env('UVS_DB_PORT') : null,
                'name' => $env('UVS_DB_NAME'),
                'user' => $env('UVS_DB_USER'),
                'password' => $env('UVS_DB_PASSWORD'),
            ], static fn ($value) => $value !== null),
            'storage_path' => $env('UVS_STORAGE_PATH'),
            'turnstile' => array_filter([
                'mode' => $env('UVS_TURNSTILE_MODE'),
                'site_key' => $env('UVS_TURNSTILE_SITE_KEY'),
                'secret_key' => $env('UVS_TURNSTILE_SECRET_KEY'),
            ], static fn ($value) => $value !== null),
            'mail' => array_filter([
                'transport' => $env('UVS_MAIL_TRANSPORT'),
                'from_address' => $env('UVS_MAIL_FROM'),
            ], static fn ($value) => $value !== null),
        ];
        if ($env('UVS_SIGNUP_MIN_SECONDS') !== null) {
            $values['signup'] = ['min_seconds' => max(0, (int) $env('UVS_SIGNUP_MIN_SECONDS'))];
        }
        return $values;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value ?? $default;
    }

    public function env(): string
    {
        return (string) $this->values['env'];
    }

    public function isProduction(): bool
    {
        return $this->env() === 'production';
    }

    public function isTest(): bool
    {
        return $this->env() === 'test';
    }

    public function source(): ?string
    {
        return $this->source;
    }

    /**
     * Whether the dynamic application can run. Static pages never depend on this.
     */
    public function isUsable(): bool
    {
        return $this->problems === [];
    }

    /**
     * Configuration problems, described without secret values.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * @return list<string>
     */
    private function validate(): array
    {
        $problems = [];
        if ($this->source === null) {
            $problems[] = 'No configuration file or environment configuration was found.';
            return $problems;
        }
        $key = $this->get('app_key');
        if (!is_string($key) || strlen($key) < 32) {
            $problems[] = 'app_key must be a random string of at least 32 characters.';
        }
        if (!is_string($this->get('db.name')) || !is_string($this->get('db.user'))) {
            $problems[] = 'Database name and user are required.';
        }
        $storage = $this->get('storage_path');
        if (!is_string($storage) || !str_starts_with($storage, '/')) {
            $problems[] = 'storage_path must be an absolute path outside the document root.';
        }
        if ($this->isProduction()) {
            $baseUrl = $this->get('base_url');
            if (!is_string($baseUrl) || !preg_match('#^https://[^/\s]+(?:/[^\s]*)?$#', $baseUrl)) {
                $problems[] = 'base_url must be the public https:// URL of the site in production.';
            }
            if (is_string($key) && str_contains(strtolower($key), 'not-secret')) {
                $problems[] = 'app_key must not reuse a development placeholder.';
            }
        }
        return $problems;
    }
}
