# Configuration, deployment, and first administrator

The community features (accounts, profiles, guide publishing, administration) need a database, a private configuration file, and a persistent storage directory. The calculators, curated guides, and reference PDFs keep working without any of them: if the configuration is missing or the database is down, community URLs show a "temporarily unavailable" page and everything else is unaffected.

## Where things live on Hostinger

Deployments replace the contents of `public_html` with the runtime package, so nothing that must survive a deployment may live there. The application looks for its private configuration in the directory **next to** the document root:

```text
/home/u000000000/domains/compendium.example/
├── public_html/              ← runtime package (replaced on every deployment)
└── uvs-private/              ← create once; never inside public_html
    ├── config.php            ← private configuration (0600)
    └── storage/              ← uploads, sessions, logs (0750, writable by PHP)
        ├── media/            ← processed avatars and guide images
        ├── sessions/         ← PHP session files
        └── logs/             ← application error log (no secrets)
```

If the hosting layout differs, set the environment variable `UVS_CONFIG_FILE` to the absolute path of the configuration file. If that variable names a missing file, the application deliberately stays unavailable rather than guessing.

### Setting it up (once)

1. In hPanel create a MySQL/MariaDB database and user. Note the database name, user, and password.
2. Over SSH (or the File Manager), create the private directories with restrictive permissions:

   ```sh
   cd ~/domains/compendium.example
   mkdir -p uvs-private/storage
   chmod 700 uvs-private
   chmod 750 uvs-private/storage
   ```

3. Copy [`config.example.php`](config.example.php) to `uvs-private/config.php`, fill in every placeholder, then `chmod 600 uvs-private/config.php`. Generate `app_key` with:

   ```sh
   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```

   Keep a copy of `app_key` with your other secrets: changing it invalidates two-factor enrolments and recovery codes.

4. Deploy the runtime package (see [development.md](development.md#runtime-deployment-package)) and run the checks and migrations from the deployed directory:

   ```sh
   cd ~/domains/compendium.example/public_html
   php bin/console config:check
   php bin/console migrate
   php bin/console migrate:status
   ```

   `config:check` reports problems without printing secret values. Run `migrate` after every deployment that adds a file under `migrations/`; it is safe to run repeatedly.

5. Create the first administrator (next section).
6. Add cron jobs in hPanel (adjust the PHP path if Hostinger shows a different one):

   ```text
   15 3 * * *  cd ~/domains/compendium.example/public_html && php bin/console media:cleanup
   45 3 * * *  cd ~/domains/compendium.example/public_html && php bin/console maintenance:prune
   ```

If SSH is not available on the plan, a one-off cron job running the same commands is an acceptable way to run `migrate`; remove it afterwards. There is intentionally no web-based installer or migration endpoint.

## Creating the first administrator (Ultraviolet)

The owner account is created from the command line, so no setup page is ever exposed on the web. The command refuses to run once any administrator exists. The username `Ultraviolet` (and names containing it) is reserved, so nobody can register it through the public signup form first.

```sh
cd ~/domains/compendium.example/public_html
php bin/console admin:create --username=Ultraviolet --email=YOUR-ADDRESS@example.com
```

The command prompts twice for the password without echoing it, so it never appears in shell history or the process list. Passwords given as `--password=…` are refused. For non-interactive use, pipe it on standard input instead: `php bin/console admin:create --username=Ultraviolet --email=… --password-stdin < some-private-file`.

Then sign in at `/account/login/` and turn on two-factor authentication under **Account → Security**. Save the recovery codes somewhere safe.

### Recovery commands

All are CLI-only and recorded in the audit log:

| Command | Purpose |
| --- | --- |
| `php bin/console user:password USERNAME` | Set a new password (prompted) and sign out all sessions. |
| `php bin/console user:mfa-disable USERNAME` | Remove two-factor authentication after a lost device. |
| `php bin/console user:set-role USERNAME member\|admin` | Promote or demote (refuses to demote the last admin). |
| `php bin/console user:delete USERNAME --confirm=USERNAME` | Delete an account, its profile, characters, and images (privacy requests). |
| `php bin/console rate-limits:clear` | Clear throttling counters after a false lockout. |

## Cloudflare Turnstile

Turnstile protects signup and password-reset requests. It is free and needs a Cloudflare account but not Cloudflare DNS.

1. In the Cloudflare dashboard open **Turnstile → Add widget**. Enter the site's hostname, choose the **Managed** widget mode, and create it.
2. Put the **site key** and **secret key** in `config.php` under `turnstile` with `mode => 'enabled'`.
3. Visit `/account/signup/`; the widget should appear. **Admin → Settings** shows the Turnstile state (never the keys).

Behaviour:

- Tokens are verified server-side against Cloudflare's Siteverify API with a 5-second timeout. A timeout, network error, unexpected response, wrong action, or wrong hostname counts as a failure.
- **Production fails closed.** If Turnstile is missing keys, disabled, or set to test mode while `env` is `production`, signup and password reset are shown as unavailable instead of running unprotected.
- `mode => 'test'` (used by Docker development and the automated tests) swaps in an offline verifier that accepts only Cloudflare's documented dummy token. It is impossible to select in production.

The signup form additionally uses a hidden honeypot field, a signed minimum-completion-time check, and per-IP rate limiting. These supplement Turnstile; they do not replace it.

## Email (optional)

Password-reset links, approval notices, and email-change notices are sent over plain SMTP using PHPMailer, so any mailbox works — for example the mailbox included with the hosting plan (Hostinger: `smtp.hostinger.com`, port 465 with `ssl` or 587 with `tls`). Configure the `mail` section of `config.php`. Without it:

- the sign-in page hides "Forgot your password?", the reset page explains reset by email is unavailable, and administrators can still reset passwords with `user:password`;
- reset tokens are never generated or displayed.

Development and tests use `transport => 'log'`, which writes messages to `storage/mail/` inside the container (`docker compose exec web-test sh -c 'cat /var/uvs/storage/mail/*.eml'`). That transport is ignored in production.

## Upload limits

Hard ceilings come from `config.php` (`media` section) and PHP's `upload_max_filesize`; administrators can lower the effective limits in **Admin → Settings**:

| Limit | Default | Hard ceiling |
| --- | --- | --- |
| Source image size | 8 MB | 10 MB (and PHP's `upload_max_filesize`) |
| Storage per member | 50 MB | 200 MB |
| Images per guide | 20 | 40 |
| Processed image size | — | 2 MB after compression |
| Source pixels | — | 40 megapixels (checked before decoding) |
| Guide image width/height | — | 1600 px (resized down) |
| Avatar | — | 256 × 256, centre-cropped |

On Hostinger set `upload_max_filesize` and `post_max_size` in hPanel's PHP configuration to at least the source image size you allow (for example 10M and 12M).

## Backups

Back up, together and regularly:

1. the database (hPanel backups or `mysqldump`);
2. `uvs-private/storage/media/` (uploaded images — not in Git and not in the runtime package);
3. `uvs-private/config.php` (store it with your other secrets, not in the repository).

`storage/sessions/` and `storage/logs/` do not need backing up. Restoring the database without the matching media leaves broken images; restoring media without the database leaves orphaned files that `media:cleanup` removes after its grace period.

## Production checklist

- [ ] `uvs-private/config.php` exists outside `public_html`, mode 600, with real secrets.
- [ ] `php bin/console config:check` reports no problems and the storage directory is writable.
- [ ] `php bin/console migrate:status` shows every migration as `applied`.
- [ ] HTTPS is enforced for the whole site (session cookies are `Secure` and use the `__Host-` prefix in production).
- [ ] `/src/App.php`, `/vendor/autoload.php`, `/bin/console`, `/templates/`, `/includes/helpers.php`, and `/migrations/` return 403 or 404.
- [ ] Turnstile keys configured and the widget renders on `/account/signup/`.
- [ ] SMTP configured (optional) and a test reset email arrives.
- [ ] The `Ultraviolet` administrator exists and has two-factor authentication enabled.
- [ ] Cron jobs for `media:cleanup` and `maintenance:prune` are scheduled.
- [ ] Backups include the database and `uvs-private/storage/media/`.
- [ ] If Hostinger's CDN is enabled, confirm it does not cache pages for signed-in visitors (they are sent with `Cache-Control: private, no-store`) and that it passes the session cookie.
