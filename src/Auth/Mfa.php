<?php

declare(strict_types=1);

namespace Uvs\Auth;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Uvs\Database;
use Uvs\Security\SecretBox;
use Uvs\Support\SystemClock;

/**
 * RFC 6238 TOTP two-factor authentication using spomky-labs/otphp.
 *
 * Seeds are encrypted at rest with libsodium; recovery codes are single-use and
 * stored only as keyed hashes; an accepted time step cannot be replayed.
 */
final class Mfa
{
    public const ISSUER = "UV's Compendium";
    public const RECOVERY_CODE_COUNT = 10;
    private const RECOVERY_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(
        private readonly Database $db,
        private readonly SecretBox $box,
        private readonly string $recoveryKey,
    ) {
    }

    public static function newSecret(): string
    {
        return Base32::encodeUpperUnpadded(random_bytes(20));
    }

    private static function totp(string $secret): TOTP
    {
        return TOTP::createFromSecret($secret, new SystemClock());
    }

    public static function provisioningUri(string $secret, string $username): string
    {
        $totp = self::totp($secret);
        $totp->setLabel($username);
        $totp->setIssuer(self::ISSUER);
        return $totp->getProvisioningUri();
    }

    public static function qrSvg(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 2), new SvgImageBackEnd()));
        $svg = $writer->writeString($uri);
        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /**
     * Returns the matched 30-second time step, or null. Steps at or before
     * $lastStep are refused so a code cannot be reused.
     */
    public static function matchStep(string $secret, string $code, ?int $lastStep, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $totp = self::totp($secret);
        $now ??= time();
        $current = intdiv($now, $totp->getPeriod());
        foreach ([$current - 1, $current, $current + 1] as $step) {
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals($totp->at($step * $totp->getPeriod()), $code)) {
                return $step;
            }
        }
        return null;
    }

    /**
     * Enables MFA with a confirmed secret and returns fresh recovery codes.
     *
     * @return list<string>
     */
    public function enable(int $userId, string $secret, int $step): array
    {
        return $this->db->transaction(function () use ($userId, $secret, $step): array {
            $this->db->execute(
                'UPDATE users SET mfa_secret = :secret, mfa_enabled_at = :now, mfa_last_step = :step,
                                  auth_epoch = auth_epoch + 1, updated_at = :now WHERE id = :id',
                ['secret' => $this->box->seal($secret), 'now' => Database::now(), 'step' => $step, 'id' => $userId],
            );
            return $this->replaceRecoveryCodes($userId);
        });
    }

    public function disable(int $userId): void
    {
        $this->db->transaction(function () use ($userId): void {
            $this->db->execute(
                'UPDATE users SET mfa_secret = NULL, mfa_enabled_at = NULL, mfa_last_step = NULL,
                                  auth_epoch = auth_epoch + 1, updated_at = :now WHERE id = :id',
                ['now' => Database::now(), 'id' => $userId],
            );
            $this->db->execute('DELETE FROM mfa_recovery_codes WHERE user_id = :id', ['id' => $userId]);
        });
    }

    /**
     * Verifies a TOTP or recovery code for an enrolled user, consuming it.
     *
     * @param array<string, mixed> $user
     * @return 'totp'|'recovery'|null
     */
    public function verify(array $user, string $code): ?string
    {
        if ($user['mfa_secret'] === null) {
            return null;
        }
        $secret = $this->box->open((string) $user['mfa_secret']);
        $lastStep = $user['mfa_last_step'] === null ? null : (int) $user['mfa_last_step'];
        $step = self::matchStep($secret, $code, $lastStep);
        if ($step !== null) {
            // The conditional update makes concurrent replays of one code lose the race.
            $updated = $this->db->execute(
                'UPDATE users SET mfa_last_step = :step WHERE id = :id AND (mfa_last_step IS NULL OR mfa_last_step < :step)',
                ['step' => $step, 'id' => (int) $user['id']],
            );
            return $updated === 1 ? 'totp' : null;
        }
        $normalized = self::normalizeRecoveryCode($code);
        if ($normalized === null) {
            return null;
        }
        $used = $this->db->execute(
            'UPDATE mfa_recovery_codes SET used_at = :now WHERE user_id = :id AND code_hash = :hash AND used_at IS NULL',
            ['now' => Database::now(), 'id' => (int) $user['id'], 'hash' => $this->hashRecoveryCode($normalized)],
        );
        return $used === 1 ? 'recovery' : null;
    }

    /**
     * @return list<string>
     */
    public function replaceRecoveryCodes(int $userId): array
    {
        $codes = [];
        $this->db->execute('DELETE FROM mfa_recovery_codes WHERE user_id = :id', ['id' => $userId]);
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = self::randomRecoveryCode();
            $codes[] = $code;
            $this->db->execute(
                'INSERT INTO mfa_recovery_codes (user_id, code_hash, created_at) VALUES (:id, :hash, :now)',
                ['id' => $userId, 'hash' => $this->hashRecoveryCode((string) self::normalizeRecoveryCode($code)), 'now' => Database::now()],
            );
        }
        return $codes;
    }

    public function remainingRecoveryCodes(int $userId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM mfa_recovery_codes WHERE user_id = :id AND used_at IS NULL', ['id' => $userId]);
    }

    private static function randomRecoveryCode(): string
    {
        $alphabet = self::RECOVERY_ALPHABET;
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return substr($code, 0, 5) . '-' . substr($code, 5);
    }

    public static function normalizeRecoveryCode(string $code): ?string
    {
        $code = strtolower((string) preg_replace('/[\s-]+/', '', $code));
        return preg_match('/^[' . self::RECOVERY_ALPHABET . ']{10}$/', $code) ? $code : null;
    }

    private function hashRecoveryCode(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, $this->recoveryKey);
    }
}
