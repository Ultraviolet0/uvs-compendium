<?php

declare(strict_types=1);

namespace Uvs\Security\Turnstile;

/**
 * Offline verifier for local development and automated tests. It is only
 * constructed when the configuration explicitly selects test mode outside
 * production. It accepts Cloudflare's documented dummy token and nothing else.
 */
final class TestVerifier implements Verifier
{
    public const PASSING_TOKEN = 'XXXX.DUMMY.TOKEN.XXXX';

    public function verify(string $token, string $remoteIp, string $action): Result
    {
        return hash_equals(self::PASSING_TOKEN, $token)
            ? new Result(true)
            : new Result(false, ['test-mode-rejected']);
    }
}
