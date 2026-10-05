<?php

declare(strict_types=1);

namespace Uvs\Security\Turnstile;

/**
 * Server-side Turnstile token verification against Cloudflare's Siteverify API.
 * Any transport error, timeout, or unexpected response is treated as failure.
 */
final class CloudflareVerifier implements Verifier
{
    public const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** @var callable(string, array<string, string>, int): ?string */
    private $transport;

    /**
     * @param null|callable(string, array<string, string>, int): ?string $transport
     */
    public function __construct(
        private readonly string $secret,
        private readonly int $timeout = 5,
        private readonly ?string $expectedHostname = null,
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? self::httpPost(...);
    }

    public function verify(string $token, string $remoteIp, string $action): Result
    {
        if ($token === '' || strlen($token) > 2048) {
            return new Result(false, ['missing-input-response']);
        }
        $body = ($this->transport)(self::ENDPOINT, [
            'secret' => $this->secret,
            'response' => $token,
            'remoteip' => $remoteIp,
            'idempotency_key' => bin2hex(random_bytes(16)),
        ], $this->timeout);
        if (!is_string($body)) {
            return new Result(false, ['transport-error']);
        }
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['success'] ?? null) !== true) {
            $codes = is_array($data['error-codes'] ?? null) ? array_values(array_filter($data['error-codes'], 'is_string')) : [];
            return new Result(false, $codes === [] ? ['verification-failed'] : $codes);
        }
        // A token minted for another form, or on another site using the same
        // keys, is refused: the action and hostname must be present and match.
        $actual = $data['action'] ?? null;
        if ($action === '' || !is_string($actual) || $actual === '' || !hash_equals($action, $actual)) {
            return new Result(false, ['action-mismatch']);
        }
        if ($this->expectedHostname !== null) {
            $hostname = $data['hostname'] ?? null;
            if (!is_string($hostname) || $hostname === ''
                || !hash_equals(strtolower($this->expectedHostname), strtolower($hostname))) {
                return new Result(false, ['hostname-mismatch']);
            }
        }
        return new Result(true);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function httpPost(string $url, array $fields, int $timeout): ?string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($fields),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $result = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            return is_string($result) && $status === 200 ? $result : null;
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'timeout' => $timeout,
            'ignore_errors' => false,
        ]]);
        $result = @file_get_contents($url, false, $context);
        return is_string($result) ? $result : null;
    }
}
