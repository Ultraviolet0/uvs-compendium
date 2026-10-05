<?php

declare(strict_types=1);

namespace Uvs\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
        private ?string $file = null,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param array<mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function file(string $path, array $headers): self
    {
        return new self('', 200, $headers, $path);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            uvs_security_headers();
            if (!isset($this->headers['Cache-Control'])) {
                $this->headers['Cache-Control'] = 'private, no-store';
            }
            header('Vary: Cookie');
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . str_replace(["\r", "\n"], '', $value));
            }
        }
        if ($this->file !== null) {
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}
