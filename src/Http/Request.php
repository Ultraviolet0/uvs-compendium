<?php

declare(strict_types=1);

namespace Uvs\Http;

use Uvs\Config;
use Uvs\Security\ClientIp;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $basePrefix,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $files = [],
        private readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/router.php');
        $prefix = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $uriPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($prefix !== '' && str_starts_with($uriPath, $prefix . '/')) {
            $uriPath = substr($uriPath, strlen($prefix));
        }
        $path = rawurldecode($uriPath);
        if ($path === '' || $path[0] !== '/' || str_contains($path, "\0")) {
            $path = '/';
        }
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $prefix,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /**
     * @return array<mixed>
     */
    public function inputArray(string $key): array
    {
        $value = $this->post[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    public function queryInt(string $key, int $default = 1, int $min = 1, int $max = 100000): int
    {
        $value = filter_var($this->query[$key] ?? null, FILTER_VALIDATE_INT);
        return $value === false || $value === null ? $default : max($min, min($max, $value));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) && isset($file['error']) && !is_array($file['error']) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function server(string $key): ?string
    {
        $value = $this->server[$key] ?? null;
        return is_scalar($value) ? (string) $value : null;
    }

    public function ip(Config $config): string
    {
        $trusted = $config->get('trusted_proxies', []);
        return ClientIp::resolve($this->server, is_array($trusted) ? array_values($trusted) : [],
            is_string($config->get('client_ip_header')) ? $config->get('client_ip_header') : null);
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept'), 'application/json')
            || $this->header('X-Requested-With') === 'fetch';
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        return ($https !== '' && $https !== 'off') || (int) ($this->server['SERVER_PORT'] ?? 0) === 443;
    }

    /** Relative path from the current page back to the application root. */
    public function basePath(): string
    {
        $segments = substr_count(rtrim($this->path, '/'), '/');
        if (!str_ends_with($this->path, '/')) {
            $segments = max(0, $segments - 1);
        }
        return str_repeat('../', $segments);
    }

    /** Absolute path (from the host root) for an application-relative path. */
    public function url(string $path): string
    {
        return $this->basePrefix . '/' . ltrim($path, '/');
    }
}
