<?php

declare(strict_types=1);

namespace Uvs\Security;

use Uvs\Database;

/**
 * Fixed-window rate limiter stored in the database.
 *
 * Buckets are keyed by an HMAC of the scope and identifier, so raw IP addresses,
 * usernames, and email addresses are never stored. Rows expire with their window.
 */
final class RateLimiter
{
    /** @var array<string, array{limit: int, window: int}> */
    public const LIMITS = [
        'login.ip' => ['limit' => 20, 'window' => 900],
        'login.account' => ['limit' => 8, 'window' => 900],
        'mfa.user' => ['limit' => 8, 'window' => 900],
        'signup.ip' => ['limit' => 6, 'window' => 3600],
        'reset.ip' => ['limit' => 6, 'window' => 3600],
        'reset.account' => ['limit' => 3, 'window' => 3600],
        'guide.submit' => ['limit' => 12, 'window' => 86400],
        'guide.create' => ['limit' => 20, 'window' => 86400],
        'media.upload' => ['limit' => 60, 'window' => 3600],
        'preview.user' => ['limit' => 240, 'window' => 600],
        'password.change' => ['limit' => 6, 'window' => 900],
    ];

    public function __construct(private readonly Database $db, private readonly string $key)
    {
    }

    /**
     * Records one attempt and reports whether it is within the limit.
     */
    public function hit(string $scope, string $identifier): bool
    {
        [$limit, $window] = $this->limits($scope);
        $bucket = $this->bucket($scope, $identifier);
        $now = time();
        $windowStart = $now - ($now % $window);
        $expires = gmdate('Y-m-d H:i:s', $windowStart + $window);
        $this->db->execute(
            'INSERT INTO rate_limits (bucket, window_start, hits, expires_at) VALUES (:bucket, :start, 1, :expires)
             ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1),
                                     expires_at = VALUES(expires_at),
                                     window_start = VALUES(window_start)',
            ['bucket' => $bucket, 'start' => $windowStart, 'expires' => $expires],
        );
        if (random_int(1, 50) === 1) {
            $this->purgeExpired();
        }
        $hits = (int) $this->db->value('SELECT hits FROM rate_limits WHERE bucket = :bucket', ['bucket' => $bucket]);
        return $hits <= $limit;
    }

    /** Reports whether a scope is currently over its limit without recording a hit. */
    public function tooMany(string $scope, string $identifier): bool
    {
        [$limit, $window] = $this->limits($scope);
        $now = time();
        $hits = $this->db->value(
            'SELECT hits FROM rate_limits WHERE bucket = :bucket AND window_start = :start',
            ['bucket' => $this->bucket($scope, $identifier), 'start' => $now - ($now % $window)],
        );
        return $hits !== null && (int) $hits >= $limit;
    }

    public function clear(string $scope, string $identifier): void
    {
        $this->db->execute('DELETE FROM rate_limits WHERE bucket = :bucket', ['bucket' => $this->bucket($scope, $identifier)]);
    }

    public function purgeExpired(): int
    {
        return $this->db->execute('DELETE FROM rate_limits WHERE expires_at < :now', ['now' => Database::now()]);
    }

    /**
     * @return array{int, int}
     */
    private function limits(string $scope): array
    {
        $config = self::LIMITS[$scope] ?? throw new \InvalidArgumentException("Unknown rate-limit scope {$scope}");
        return [$config['limit'], $config['window']];
    }

    private function bucket(string $scope, string $identifier): string
    {
        return hash_hmac('sha256', $scope . "\0" . mb_strtolower(trim($identifier)), $this->key);
    }
}
