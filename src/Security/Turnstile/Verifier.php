<?php

declare(strict_types=1);

namespace Uvs\Security\Turnstile;

interface Verifier
{
    public function verify(string $token, string $remoteIp, string $action): Result;
}
