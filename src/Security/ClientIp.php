<?php

declare(strict_types=1);

namespace Uvs\Security;

/**
 * Resolves the client address. Forwarding headers are only honoured when the
 * direct peer is an explicitly configured trusted proxy.
 */
final class ClientIp
{
    /**
     * @param array<string, mixed> $server
     * @param list<string> $trustedProxies IPs or CIDR ranges
     */
    public static function resolve(array $server, array $trustedProxies, ?string $header): string
    {
        $remote = (string) ($server['REMOTE_ADDR'] ?? '0.0.0.0');
        if ($header === null || $header === '' || !self::matchesAny($remote, $trustedProxies)) {
            return $remote;
        }
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        $value = $server[$key] ?? null;
        if (!is_string($value) || $value === '') {
            return $remote;
        }
        // For X-Forwarded-For, walk from the right and skip trusted hops.
        $candidates = array_reverse(array_map('trim', explode(',', $value)));
        foreach ($candidates as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }
            if (!self::matchesAny($candidate, $trustedProxies)) {
                return $candidate;
            }
        }
        return $remote;
    }

    /**
     * @param list<string> $ranges
     */
    public static function matchesAny(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (self::matches($ip, (string) $range)) {
                return true;
            }
        }
        return false;
    }

    public static function matches(string $ip, string $range): bool
    {
        $address = @inet_pton($ip);
        if ($address === false) {
            return false;
        }
        [$subnet, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
        $network = @inet_pton($subnet);
        if ($network === false || strlen($network) !== strlen($address)) {
            return false;
        }
        $maxBits = strlen($address) * 8;
        $bits = $bits === null ? $maxBits : (int) $bits;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }
        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }
}
