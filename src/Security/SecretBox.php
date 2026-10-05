<?php

declare(strict_types=1);

namespace Uvs\Security;

use RuntimeException;

/**
 * Authenticated symmetric encryption for small secrets at rest (TOTP seeds),
 * using libsodium's XSalsa20-Poly1305 secretbox.
 */
final class SecretBox
{
    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Invalid secret box key length.');
        }
    }

    public function seal(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . sodium_bin2base64($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key),
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public function open(string $sealed): string
    {
        if (!str_starts_with($sealed, 'v1:')) {
            throw new RuntimeException('Unsupported sealed value.');
        }
        $raw = sodium_base642bin(substr($sealed, 3), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plaintext === false) {
            throw new RuntimeException('Sealed value failed authentication.');
        }
        return $plaintext;
    }
}
