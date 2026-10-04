<?php

declare(strict_types=1);

namespace Uvs\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uvs\Auth\Mfa;
use Uvs\Config;
use Uvs\Logger;
use Uvs\Security\ClientIp;
use Uvs\Security\FormTimer;
use Uvs\Security\SecretBox;
use Uvs\Security\Turnstile\CloudflareVerifier;
use Uvs\Security\Turnstile\TestVerifier;
use Uvs\Security\Turnstile\Turnstile;

final class SecurityTest extends TestCase
{
    private const KEY = 'unit-test-key-0123456789abcdef0123456789';

    public function testFormTimerRejectsFastForgedAndExpiredSubmissions(): void
    {
        $timer = new FormTimer(self::KEY);
        $token = $timer->issue('signup', 1_800_000_000);
        self::assertSame('too_fast', $timer->check('signup', $token, 3, 7200, 1_800_000_001));
        self::assertSame('ok', $timer->check('signup', $token, 3, 7200, 1_800_000_010));
        self::assertSame('expired', $timer->check('signup', $token, 3, 7200, 1_800_100_000));
        self::assertSame('invalid', $timer->check('login', $token, 3, 7200, 1_800_000_010));
        self::assertSame('invalid', $timer->check('signup', '1799999000.' . str_repeat('a', 64), 3, 7200, 1_800_000_010));
        self::assertSame('invalid', $timer->check('signup', null, 3, 7200));
    }

    public function testClientIpIgnoresForwardingHeadersFromUntrustedPeers(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'];
        self::assertSame('203.0.113.9', ClientIp::resolve($server, [], 'X-Forwarded-For'));
        self::assertSame('203.0.113.9', ClientIp::resolve($server, ['10.0.0.0/8'], 'X-Forwarded-For'));
        $proxied = ['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.9.9.9'];
        self::assertSame('198.51.100.7', ClientIp::resolve($proxied, ['10.0.0.0/8'], 'X-Forwarded-For'));
        $spoofed = ['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip'];
        self::assertSame('10.1.2.3', ClientIp::resolve($spoofed, ['10.0.0.0/8'], 'X-Forwarded-For'));
        self::assertTrue(ClientIp::matches('2001:db8::5', '2001:db8::/32'));
        self::assertFalse(ClientIp::matches('2001:db9::5', '2001:db8::/32'));
    }

    public function testSecretBoxRoundTripAndTamperDetection(): void
    {
        $box = new SecretBox(str_repeat('k', 32));
        $sealed = $box->seal('JBSWY3DPEHPK3PXP');
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $sealed);
        self::assertSame('JBSWY3DPEHPK3PXP', $box->open($sealed));
        $this->expectException(\RuntimeException::class);
        (new SecretBox(str_repeat('x', 32)))->open($sealed);
    }

    public function testTotpMatchingAndReplayProtection(): void
    {
        $secret = Mfa::newSecret();
        $now = 1_800_000_000;
        $code = \OTPHP\TOTP::createFromSecret($secret, new \Uvs\Support\SystemClock())->at($now);
        $step = Mfa::matchStep($secret, $code, null, $now);
        self::assertSame(intdiv($now, 30), $step);
        self::assertNull(Mfa::matchStep($secret, $code, $step, $now), 'a used step must not be accepted again');
        self::assertNull(Mfa::matchStep($secret, '000000', null, $now + 3600));
        self::assertNull(Mfa::matchStep($secret, 'abcdef', null, $now));
        self::assertSame('abcdefghjk', Mfa::normalizeRecoveryCode('ABCDE-FGHJK'));
        self::assertNull(Mfa::normalizeRecoveryCode('short'));
    }

    public function testTurnstileFailsClosedInProduction(): void
    {
        $production = new Config(['env' => 'production', 'turnstile' => ['mode' => 'test']]);
        self::assertSame('misconfigured', Turnstile::fromConfig($production)->status());
        self::assertFalse(Turnstile::fromConfig($production)->isAvailable());
        $disabled = new Config(['env' => 'production', 'turnstile' => ['mode' => 'disabled']]);
        self::assertSame('misconfigured', Turnstile::fromConfig($disabled)->status());
        $noKeys = new Config(['env' => 'production', 'turnstile' => ['mode' => 'enabled']]);
        self::assertSame('misconfigured', Turnstile::fromConfig($noKeys)->status());
        $configured = new Config(['env' => 'production', 'turnstile' => ['mode' => 'enabled', 'site_key' => 'site', 'secret_key' => 'secret']]);
        self::assertSame('enabled', Turnstile::fromConfig($configured)->status());
    }

    public function testTurnstileTestModeOnlyOutsideProduction(): void
    {
        $test = Turnstile::fromConfig(new Config(['env' => 'test', 'turnstile' => ['mode' => 'test']]));
        self::assertSame('test', $test->status());
        self::assertTrue($test->verify(TestVerifier::PASSING_TOKEN, '127.0.0.1', 'signup')->success);
        self::assertFalse($test->verify('anything-else', '127.0.0.1', 'signup')->success);
        self::assertFalse($test->verify(null, '127.0.0.1', 'signup')->success);
    }

    public function testCloudflareVerifierHandlesResponsesAndFailures(): void
    {
        $seen = [];
        $ok = new CloudflareVerifier('secret', 5, 'compendium.example', function (string $url, array $fields) use (&$seen): string {
            $seen = $fields;
            return json_encode(['success' => true, 'action' => 'signup', 'hostname' => 'compendium.example']);
        });
        self::assertTrue($ok->verify('token', '198.51.100.7', 'signup')->success);
        self::assertSame('secret', $seen['secret']);
        self::assertSame('token', $seen['response']);
        self::assertSame('198.51.100.7', $seen['remoteip']);

        $fail = new CloudflareVerifier('secret', 5, null, fn () => json_encode(['success' => false, 'error-codes' => ['invalid-input-response']]));
        $result = $fail->verify('token', '198.51.100.7', 'signup');
        self::assertFalse($result->success);
        self::assertSame(['invalid-input-response'], $result->errors);

        self::assertFalse((new CloudflareVerifier('s', 5, null, fn () => null))->verify('token', '1.2.3.4', 'signup')->success, 'timeouts fail closed');
        self::assertFalse((new CloudflareVerifier('s', 5, null, fn () => 'not json'))->verify('token', '1.2.3.4', 'signup')->success);
        self::assertFalse((new CloudflareVerifier('s', 5, null, fn () => json_encode(['success' => true, 'action' => 'login'])))->verify('token', '1.2.3.4', 'signup')->success);
        self::assertFalse((new CloudflareVerifier('s', 5, 'a.example', fn () => json_encode(['success' => true, 'hostname' => 'b.example'])))->verify('t', '1.2.3.4', 'signup')->success);
        self::assertFalse((new CloudflareVerifier('s', 5, null, fn () => '{}'))->verify('', '1.2.3.4', 'signup')->success);
    }

    public function testTurnstileRequiresExactActionAndHostname(): void
    {
        $verify = static function (array $response, ?string $expectedHost = 'compendium.example', string $action = 'signup') {
            return (new CloudflareVerifier('secret', 5, $expectedHost, fn () => json_encode(['success' => true] + $response)))
                ->verify('token', '198.51.100.7', $action);
        };
        $valid = ['action' => 'signup', 'hostname' => 'compendium.example'];
        self::assertTrue($verify($valid)->success, 'valid response');
        self::assertTrue($verify(['hostname' => 'Compendium.Example'] + $valid)->success, 'hostnames compare case-insensitively');

        $cases = [
            'missing action' => [['hostname' => 'compendium.example'], 'action-mismatch'],
            'empty action' => [['action' => ''] + $valid, 'action-mismatch'],
            'non-string action' => [['action' => ['signup']] + $valid, 'action-mismatch'],
            'mismatched action' => [['action' => 'login'] + $valid, 'action-mismatch'],
            'action prefix' => [['action' => 'signup2'] + $valid, 'action-mismatch'],
            'missing hostname' => [['action' => 'signup'], 'hostname-mismatch'],
            'empty hostname' => [['hostname' => ''] + $valid, 'hostname-mismatch'],
            'null hostname' => [['hostname' => null] + $valid, 'hostname-mismatch'],
            'mismatched hostname' => [['hostname' => 'evil.example'] + $valid, 'hostname-mismatch'],
            'subdomain hostname' => [['hostname' => 'compendium.example.evil.test'] + $valid, 'hostname-mismatch'],
        ];
        foreach ($cases as $name => [$response, $code]) {
            $result = $verify($response);
            self::assertFalse($result->success, $name);
            self::assertSame([$code], $result->errors, $name);
        }
        self::assertFalse($verify($valid, 'compendium.example', '')->success, 'an empty expected action never matches');
        // Without a configured hostname (no base_url host), only the action is enforced.
        self::assertTrue($verify(['action' => 'signup'], null)->success);
        self::assertFalse($verify(['hostname' => 'compendium.example'], null)->success);
    }

    public function testProductionConfigurationRequiresSecureValues(): void
    {
        $missing = new Config(['env' => 'production']);
        self::assertFalse($missing->isUsable());
        $insecure = new Config([
            'env' => 'production', 'base_url' => 'http://compendium.example', 'app_key' => 'local-development-key-not-secret-0000000000000000',
            'db' => ['name' => 'db', 'user' => 'u'], 'storage_path' => '/srv/private/storage',
        ], 'test');
        self::assertFalse($insecure->isUsable());
        self::assertCount(2, $insecure->problems());
        foreach ($insecure->problems() as $problem) {
            self::assertStringNotContainsString('local-development-key', $problem);
        }
        $good = new Config([
            'env' => 'production', 'base_url' => 'https://compendium.example', 'app_key' => bin2hex(random_bytes(32)),
            'db' => ['name' => 'db', 'user' => 'u', 'password' => 'p'], 'storage_path' => '/srv/private/storage',
        ], 'test');
        self::assertTrue($good->isUsable(), implode(' ', $good->problems()));
    }

    public function testLoggerRedactsSecrets(): void
    {
        $redacted = Logger::redact(['password' => 'hunter2', 'nested' => ['reset_token' => 'abc', 'csrf' => 'x'], 'slug' => 'ok']);
        self::assertSame('[redacted]', $redacted['password']);
        self::assertSame('[redacted]', $redacted['nested']['reset_token']);
        self::assertSame('[redacted]', $redacted['nested']['csrf']);
        self::assertSame('ok', $redacted['slug']);
    }
}
