# Security model

This page records the security decisions behind the community features and the assumptions they rely on. Report suspected problems privately to the site owner rather than in a public issue.

## Assumptions

- The whole site is served over HTTPS in production, and the configured `base_url` is the real public HTTPS address.
- The private configuration file and storage directory live outside the document root and are readable only by the site's PHP user.
- Apache honours `.htaccess` (`mod_rewrite`, `mod_headers`, `Require`). Denial of source directories is also enforced by deny-all `.htaccess` files inside each private directory of the runtime package. If a host ignored `.htaccess`, requesting a file in `src/` would only define a class and a template would fail without its controller, but that is not a safeguard to rely on: verify denial on staging (see the checklist in [configuration.md](configuration.md#production-checklist)).
- Only proxies listed in `trusted_proxies` are trusted for client IP headers.

## Accounts and sessions

- Passwords: `password_hash()` with Argon2id when PHP provides it (bcrypt otherwise, with its 72-byte limit enforced instead of silently truncated); minimum 10 characters, maximum 256; a small common-password deny-list; transparent rehash on sign-in when PHP reports it is needed. Plaintext passwords are never stored or logged, and the CLI refuses passwords on the command line.
- Usernames: 3–24 ASCII letters, digits, `_`, or `-`; unique case-insensitively with the chosen casing preserved; staff-like and owner names (`admin`, `root`, `moderator`, `Ultraviolet`, …) are reserved.
- Sign-in failures use one generic message, and unknown identifiers spend comparable hashing time. Signup necessarily reveals whether a username is taken (usernames are public); a taken email gets a deliberately vague message. Password-reset requests always show the same response.
- Sessions: native PHP sessions with `use_strict_mode`, cookie-only IDs, `HttpOnly`, `SameSite=Lax`, `Secure` and the `__Host-` prefix in production, a 2-hour idle limit (1 hour for administrators), and a 12-hour absolute limit. The ID is regenerated at sign-in, at the two-factor step, and when the member's status or role changes.
- Revocation: each account has an `auth_epoch`; sessions store the epoch they were issued for. Password changes, password resets, two-factor changes, suspension, rejection, and role changes increment it, which signs out every other session immediately. Every request reloads the account, so suspension and role changes take effect on the next request.
- Pending accounts can sign in and manage their profile; rejected and suspended accounts cannot sign in.
- Two-factor authentication: RFC 6238 TOTP (30-second steps, ±1 step tolerance). Seeds are encrypted with libsodium `secretbox` using a key derived from `app_key`. An accepted time step is recorded and cannot be reused. Ten single-use recovery codes are shown once and stored as keyed hashes. Enabling or disabling requires the password (and a current code to disable).

## Requests

- Every POST, including JSON/autosave requests, must carry the session's CSRF token (form field or `X-CSRF-Token` header, constant-time comparison) and, when the browser sends `Origin`, come from the same host. State never changes on GET.
- Destructive administrator actions require POST, CSRF, an explicit confirmation checkbox, and — for permanent guide deletion — typing the guide's slug. Guides are soft-deleted first.
- All SQL uses native prepared statements with bound values; identifiers are never built from input.
- All output is escaped with `htmlspecialchars` in the right context; JSON responses escape HTML-significant characters.
- Rate limits (fixed windows, keyed by HMAC so raw IPs/emails are not stored): sign-in per IP and per account, two-factor attempts, signup per IP, reset requests per IP and per address, guide creation and submission per member, uploads, previews, and password/email changes. Expired rows are pruned automatically and by `maintenance:prune`.

## Content

- Guides are Markdown rendered by league/commonmark with `html_input: escape` and `allow_unsafe_links: false`. Links are additionally limited to `http(s)`, `mailto`, same-site paths, and fragments; external links get `rel="nofollow ugc noopener noreferrer"`. Images may only reference images uploaded to the same guide. The only embeds are YouTube videos built from a validated id on `youtube-nocookie.com`. Headings are capped below the page `h1`.
- There is no HTML sanitizer to bypass: user HTML is never rendered.
- Profile text is plain text (escaped, line breaks only). Websites must be `http(s)` and are linked with `nofollow ugc noopener noreferrer`. Emails, admin notes, and security data are never selected for public pages.

## Uploads

See [architecture.md](architecture.md#media). Images are decoded and re-encoded server-side; SVG and non-image content is rejected by content sniffing, not file extensions; file names are random 128-bit identifiers chosen by the server; storage is outside the document root; files are served through PHP with `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'; sandbox`, and `Content-Disposition: inline` with a safe name. Nothing in storage is ever executed. Ownership is checked on every delete.

## Response headers

PHP pages send:

```text
Content-Security-Policy: default-src 'self'; script-src 'self' https://challenges.cloudflare.com;
  style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';
  media-src 'self'; frame-src https://www.youtube-nocookie.com https://challenges.cloudflare.com;
  object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
X-Frame-Options: SAMEORIGIN
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()
Cross-Origin-Opener-Policy: same-origin
```

No inline scripts are used anywhere (the theme loader is an external file), so `script-src` needs no `'unsafe-inline'`. `'unsafe-inline'` remains for styles because existing guide pages use a few `style` attributes; user content cannot inject styles. The policy is exercised by the browser tests on every page type, including ES-module calculators, the editor, and YouTube embeds. Signed-in pages are sent with `Cache-Control: private, no-store` and every PHP response varies on `Cookie`.

## Logging and audit

The application log records failures with redacted context (keys that look like passwords, tokens, secrets, cookies, sessions, or codes are replaced) and never records request bodies. The audit log records who did what to which account, guide, or setting; its metadata passes through the same redaction.

## Known limitations and follow-ups

- Email verification is not implemented; manual approval and Turnstile are the safeguards against fake signups. The token table already supports an `email_verification` purpose.
- Anonymous guide submission is scaffolded (authorless guides and a disabled setting) but not offered.
- Rate limiting is per web server process group via the shared database; a determined distributed attacker is mitigated mainly by Turnstile.
- If the Hostinger CDN caches HTML for signed-in visitors despite `Cache-Control: private, no-store`, disable caching for pages with the session cookie.
