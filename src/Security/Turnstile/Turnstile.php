<?php

declare(strict_types=1);

namespace Uvs\Security\Turnstile;

use Uvs\Config;
use Uvs\Logger;

/**
 * Cloudflare Turnstile policy.
 *
 * - enabled: real widget and Siteverify; requires a site key and secret key.
 * - test: offline verifier for development/tests; refused in production.
 * - disabled: no challenge; refused in production.
 *
 * Production fails closed: anything other than a fully configured "enabled"
 * mode makes protected forms unavailable instead of unprotected.
 */
final class Turnstile
{
    public const RESPONSE_FIELD = 'cf-turnstile-response';

    public function __construct(
        private readonly string $status,
        private readonly ?string $siteKey,
        private readonly ?Verifier $verifier,
        private readonly ?Logger $logger = null,
    ) {
    }

    public static function fromConfig(Config $config, ?Logger $logger = null): self
    {
        $mode = (string) $config->get('turnstile.mode', 'enabled');
        $siteKey = $config->get('turnstile.site_key');
        $secret = $config->get('turnstile.secret_key');
        $production = $config->isProduction();

        if ($mode === 'enabled') {
            if (!is_string($siteKey) || $siteKey === '' || !is_string($secret) || $secret === '') {
                return new self('misconfigured', null, null, $logger);
            }
            $host = parse_url((string) $config->get('base_url', ''), PHP_URL_HOST);
            return new self('enabled', $siteKey, new CloudflareVerifier(
                $secret, max(1, min(15, (int) $config->get('turnstile.timeout', 5))),
                is_string($host) ? $host : null,
            ), $logger);
        }
        if ($production) {
            return new self('misconfigured', null, null, $logger);
        }
        return $mode === 'test'
            ? new self('test', null, new TestVerifier(), $logger)
            : new self('disabled', null, null, $logger);
    }

    /** 'enabled', 'test', 'disabled', or 'misconfigured'. */
    public function status(): string
    {
        return $this->status;
    }

    /** Whether forms protected by Turnstile may be offered at all. */
    public function isAvailable(): bool
    {
        return $this->status !== 'misconfigured';
    }

    public function isChallengeRequired(): bool
    {
        return $this->status === 'enabled' || $this->status === 'test';
    }

    public function siteKey(): ?string
    {
        return $this->siteKey;
    }

    public function verify(mixed $token, string $remoteIp, string $action): Result
    {
        if ($this->status === 'disabled') {
            return new Result(true);
        }
        if ($this->verifier === null) {
            return new Result(false, ['not-configured']);
        }
        $result = $this->verifier->verify(is_string($token) ? trim($token) : '', $remoteIp, $action);
        if (!$result->success && $this->logger !== null) {
            $this->logger->info('Turnstile verification failed', ['action' => $action, 'errors' => $result->errors]);
        }
        return $result;
    }
}
