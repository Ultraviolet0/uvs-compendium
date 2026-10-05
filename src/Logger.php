<?php

declare(strict_types=1);

namespace Uvs;

use Throwable;

/**
 * Minimal structured logger. Context keys that could carry credentials are
 * redacted before anything is written.
 */
final class Logger
{
    private const SENSITIVE = '/pass|secret|token|csrf|session|cookie|key|code|authorization/i';

    public function __construct(private readonly ?string $directory)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function exception(Throwable $error, string $message = 'Unhandled exception'): void
    {
        $this->error($message, [
            'type' => $error::class,
            'detail' => self::scrubText($error->getMessage()),
            'at' => basename($error->getFile()) . ':' . $error->getLine(),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE, $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            } elseif (is_string($value)) {
                $context[$key] = mb_substr($value, 0, 500);
            }
        }
        return $context;
    }

    private static function scrubText(string $text): string
    {
        // PDO messages can echo connection details; keep only the first line and mask quoted values.
        $line = strtok($text, "\n") ?: '';
        return mb_substr((string) preg_replace("/'[^']*'/", "'…'", $line), 0, 300);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $entry = json_encode([
            'time' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => self::redact($context),
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";

        if ($this->directory !== null) {
            if (!is_dir($this->directory)) {
                @mkdir($this->directory, 0750, true);
            }
            $file = $this->directory . '/app-' . gmdate('Y-m') . '.log';
            if (@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        }
        error_log(rtrim($entry));
    }
}
