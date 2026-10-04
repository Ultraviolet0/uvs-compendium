<?php

/*
 * UV's Compendium private configuration — EXAMPLE ONLY.
 *
 * Copy to the private directory OUTSIDE the web root (see docs/configuration.md),
 * for example ~/domains/example.com/uvs-private/config.php, then replace every
 * placeholder. Never commit the real file. All values below are fake.
 */

return [
    'env' => 'production',

    // Public HTTPS address of the site, used for links in emails and Turnstile hostname checks.
    'base_url' => 'https://compendium.example',

    // 64 random hex characters, e.g. `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
    // Keys rate-limit hashes, form timers, MFA secret encryption, and recovery-code hashes.
    // Changing it later invalidates MFA enrolments and recovery codes.
    'app_key' => 'REPLACE-WITH-64-RANDOM-HEX-CHARACTERS',

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u000000000_compendium',
        'user' => 'u000000000_compendium',
        'password' => 'REPLACE-WITH-THE-DATABASE-PASSWORD',
    ],

    // Persistent private storage for uploads, sessions, and logs. Must be outside
    // public_html so deployments never replace it.
    'storage_path' => '/home/u000000000/domains/compendium.example/uvs-private/storage',

    'session' => [
        'idle_timeout' => 7200,        // seconds of inactivity before sign-out
        'absolute_timeout' => 43200,   // maximum session age
        'admin_idle_timeout' => 3600,  // shorter idle limit for administrators
    ],

    // Cloudflare Turnstile (free). Production refuses anything but a fully configured "enabled" mode.
    'turnstile' => [
        'mode' => 'enabled',
        'site_key' => '0x0000000000000000000000',
        'secret_key' => 'REPLACE-WITH-THE-TURNSTILE-SECRET-KEY',
    ],

    // Optional SMTP for password-reset and approval emails. Without it, reset by email is disabled.
    'mail' => [
        'transport' => 'smtp',
        'from_address' => 'no-reply@compendium.example',
        'from_name' => "UV's Compendium",
        'smtp' => [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',      // 'tls' (STARTTLS, port 587) or 'ssl' (port 465)
            'username' => 'no-reply@compendium.example',
            'password' => 'REPLACE-WITH-THE-MAILBOX-PASSWORD',
        ],
    ],

    // Only list proxies you operate or explicitly trust. Leave empty to use the
    // direct peer address (REMOTE_ADDR).
    'trusted_proxies' => [],
    'client_ip_header' => null,   // e.g. 'CF-Connecting-IP' when behind a trusted Cloudflare proxy

    // Optional hard ceilings; administrators can lower these in Settings.
    'media' => [
        'max_upload_bytes' => 10485760,
        'max_quota_bytes' => 209715200,
        'max_images_per_guide' => 40,
    ],
];
