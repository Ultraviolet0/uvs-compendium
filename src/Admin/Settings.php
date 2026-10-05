<?php

declare(strict_types=1);

namespace Uvs\Admin;

use InvalidArgumentException;
use Uvs\Config;
use Uvs\Database;

/**
 * Administrator-editable site settings with typed defaults. Values are bounded
 * by hard limits from the private configuration; secrets never live here.
 */
final class Settings
{
    /** @var array<string, string>|null */
    private ?array $stored = null;

    public function __construct(private readonly Database $db, private readonly Config $config)
    {
    }

    /**
     * @return array<string, array{type: string, default: bool|int, label: string, help: string, min?: int, max?: int, scaffold?: bool}>
     */
    public function definitions(): array
    {
        $hardUploadMb = max(1, intdiv((int) $this->config->get('media.max_upload_bytes'), 1024 * 1024));
        $hardQuotaMb = max(1, intdiv((int) $this->config->get('media.max_quota_bytes'), 1024 * 1024));
        $hardImages = max(0, (int) $this->config->get('media.max_images_per_guide'));
        return [
            'registrations_enabled' => [
                'type' => 'bool', 'default' => true,
                'label' => 'Allow new account registrations',
                'help' => 'When off, the signup page explains that registration is closed.',
            ],
            'auto_approve_accounts' => [
                'type' => 'bool', 'default' => false,
                'label' => 'Automatically approve new accounts',
                'help' => 'Applies only to future signups. Pending, rejected, and suspended accounts are never changed.',
            ],
            'guide_submissions_enabled' => [
                'type' => 'bool', 'default' => true,
                'label' => 'Accept guide submissions',
                'help' => 'Active members can still write drafts while submissions are paused.',
            ],
            'anonymous_submissions_enabled' => [
                'type' => 'bool', 'default' => false, 'scaffold' => true,
                'label' => 'Allow anonymous guide submissions',
                'help' => 'Reserved for a later phase. The data model supports authorless guides, but no anonymous submission form exists yet, so this setting has no effect.',
            ],
            'media_max_upload_mb' => [
                'type' => 'int', 'default' => min(8, $hardUploadMb), 'min' => 1, 'max' => $hardUploadMb,
                'label' => 'Maximum image upload size (MB)',
                'help' => 'Source file limit before processing. The server hard limit is ' . $hardUploadMb . ' MB.',
            ],
            'media_quota_mb' => [
                'type' => 'int', 'default' => min(50, $hardQuotaMb), 'min' => 1, 'max' => $hardQuotaMb,
                'label' => 'Storage quota per member (MB)',
                'help' => 'Total processed image storage per account, including the avatar.',
            ],
            'media_max_images_per_guide' => [
                'type' => 'int', 'default' => min(20, $hardImages), 'min' => 0, 'max' => $hardImages,
                'label' => 'Maximum images per guide',
                'help' => 'Set to 0 to disable new guide image uploads.',
            ],
        ];
    }

    public function get(string $name): bool|int
    {
        $definition = $this->definitions()[$name] ?? throw new InvalidArgumentException("Unknown setting {$name}");
        $stored = $this->stored()[$name] ?? null;
        if ($stored === null) {
            return $definition['default'];
        }
        return $this->cast($definition, $stored);
    }

    public function bool(string $name): bool
    {
        return (bool) $this->get($name);
    }

    public function int(string $name): int
    {
        return (int) $this->get($name);
    }

    /**
     * @return array<string, bool|int>
     */
    public function all(): array
    {
        $values = [];
        foreach (array_keys($this->definitions()) as $name) {
            $values[$name] = $this->get($name);
        }
        return $values;
    }

    /**
     * Validates and stores a value. Returns the previous value.
     */
    public function set(string $name, mixed $value, ?int $actorId): bool|int
    {
        $definition = $this->definitions()[$name] ?? throw new InvalidArgumentException("Unknown setting {$name}");
        $previous = $this->get($name);
        if ($definition['type'] === 'bool') {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($normalized === null) {
                throw new InvalidArgumentException("{$definition['label']} must be on or off.");
            }
            $stored = $normalized ? '1' : '0';
        } else {
            $number = filter_var($value, FILTER_VALIDATE_INT);
            if ($number === false || $number < $definition['min'] || $number > $definition['max']) {
                throw new InvalidArgumentException("{$definition['label']} must be between {$definition['min']} and {$definition['max']}.");
            }
            $stored = (string) $number;
        }
        $this->db->execute(
            'INSERT INTO settings (name, value, updated_at, updated_by) VALUES (:name, :value, :now, :actor)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
            ['name' => $name, 'value' => $stored, 'now' => Database::now(), 'actor' => $actorId],
        );
        $this->stored = null;
        return $previous;
    }

    /**
     * @param array{type: string} $definition
     */
    private function cast(array $definition, string $value): bool|int
    {
        if ($definition['type'] === 'bool') {
            return $value === '1';
        }
        $number = (int) $value;
        return max($definition['min'] ?? $number, min($definition['max'] ?? $number, $number));
    }

    /**
     * @return array<string, string>
     */
    private function stored(): array
    {
        if ($this->stored === null) {
            $this->stored = [];
            foreach ($this->db->all('SELECT name, value FROM settings') as $row) {
                $this->stored[(string) $row['name']] = (string) $row['value'];
            }
        }
        return $this->stored;
    }
}
