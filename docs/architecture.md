# Community application architecture

UV's Compendium remains a server-rendered PHP site. The calculators and curated guides are unchanged physical pages; the community features (accounts, member profiles, guide publishing, moderation) are a small application layered beside them. There is no framework: a handful of maintained Composer libraries cover the parts that should not be hand-written.

## Request flow

```text
Apache ──► physical file or directory? ──yes──► existing page (index.php, CSS, JS, PDFs)
              │
              no
              ▼
          router.php ──► src/Http/Kernel.php ──► controller ──► template inside the shared header/footer
```

- `.htaccess` sends a request to `router.php` **only** when no file or directory matches, so `/guides/shopping/` and every calculator always win over dynamic routes. Source directories (`src`, `templates`, `migrations`, `bin`, `vendor`, `includes`, …) are denied before routing.
- `src/Http/Kernel.php` holds the explicit route table. Every application URL ends in `/`; slashless requests are redirected. Every POST is checked for a valid CSRF token and a same-site `Origin` before any controller runs.
- Errors become friendly 403/404/405/429/500/503 pages. Details go to the private log (`storage/logs/`), never to the browser.

The shared header (`includes/public_header.php`) asks `uvs_page_context()` for the signed-in member. Visitors without a session cookie never start a session or touch the database, so the static pages stay cacheable and keep working if the database is unavailable.

## Code layout

```text
router.php                 front controller (only for non-file paths)
bin/console                operator CLI (migrations, bootstrap, recovery, cleanup)
src/
  App.php                  explicit service factories; no container magic
  Config.php               private config file or environment variables
  Database.php             PDO with native prepared statements
  Http/                    Request, Response, Router, Kernel, View
  Security/                Session, Csrf, RateLimiter, ClientIp, FormTimer, SecretBox, Turnstile/
  Auth/                    Auth (current user), Gate (permissions), PasswordPolicy/Hasher, Mfa
  Users/                   UserRepository, AccountService, PasswordResetService, policies
  Guides/                  GuideRepository, GuideWorkflow, GuideStatus, MarkdownRenderer, Slugger, LineDiff
  Media/                   ImageProcessor, MediaService, YouTube
  Admin/                   Settings, AuditLog
  Mail/                    Mailer (SMTP via PHPMailer, local capture, or disabled)
  Controller/              thin HTTP controllers, including Admin/
templates/                 PHP views; every dynamic value is escaped with h()
migrations/                ordered, idempotent schema migrations
css/app.css, js/*.js       community styles and progressive enhancements
```

## Dependencies

| Package | Why |
| --- | --- |
| `league/commonmark` | Mature CommonMark parser. Raw HTML is escaped and unsafe links are dropped at the parser level; custom handling works on the AST instead of on HTML strings. |
| `phpmailer/phpmailer` | Provider-neutral SMTP with correct encoding and TLS handling. |
| `spomky-labs/otphp` | RFC 6238 TOTP generation for two-factor authentication. |
| `bacon/bacon-qr-code` | Server-side SVG QR codes for authenticator enrolment (no third-party QR service). |
| `phpunit/phpunit` (dev only) | PHP unit and database-integration tests. Never packaged. |

`composer.lock` pins every version; `config.platform.php` pins resolution to PHP 8.3. CI runs `composer validate --strict` and `composer audit`. Cryptography uses PHP's password API (`password_hash` with Argon2id when available) and libsodium; nothing is hand-rolled.

## Database

MariaDB 10.6+/MySQL 8 with InnoDB and `utf8mb4`. All times are stored in UTC. `migrations/0001_community_schema.php` creates:

| Table | Purpose |
| --- | --- |
| `users` | Account, case-insensitive unique `username_key` and `email_key`, password hash, `role` (member/moderator/admin), `status` (pending/active/rejected/suspended), member-facing `status_reason`, private `admin_note`, `auth_epoch` for session revocation, encrypted MFA seed, theme preference. |
| `user_profiles` | Optional bio, preferred game, website, Discord name, avatar. |
| `user_characters` | Optional Diablo characters; a CHECK constraint keeps Monk/Bard/Barbarian Hellfire-only. |
| `guides` | Working copy plus `review_status` (draft/in_review/needs_changes/approved/rejected) and `visibility` (private/published/hidden), `published_revision_id`, soft-delete columns, optimistic `lock_version`. |
| `guide_revisions` | Immutable snapshots at submission, resubmission, administrator edit, and publication. |
| `media` | Uploaded images: random `public_id`, owner, purpose (avatar/guide), dimensions, size, SHA-256. Files live in private storage. |
| `account_tokens` | Password-reset tokens stored as SHA-256 hashes with expiry and single use (email verification is reserved for later). |
| `mfa_recovery_codes` | Single-use recovery codes stored as keyed hashes. |
| `settings` | Administrator settings with typed defaults in code. |
| `audit_events` | Privileged actions with actor, target, time, and redacted metadata. |
| `rate_limits` | Fixed-window counters keyed by HMAC of scope and identifier (no raw IPs or emails). |
| `schema_migrations` | Applied migrations with checksums. |

Foreign keys cascade only for data owned by a user or guide; references from audit events, reviewers, and authors use `SET NULL` so history survives deletions. Uniqueness, enumerations, slug and username formats, and character class rules are enforced by the database as well as by the application.

### Migrations

`php bin/console migrate` applies new files in `migrations/` in name order, records each with a checksum, and takes a database advisory lock so two runs cannot overlap. A migration is a PHP file returning a list of SQL strings and/or callables; statements use `IF NOT EXISTS` or explicit existence checks because MySQL DDL cannot be rolled back, so an interrupted run can simply be repeated. An applied migration that later changes is reported as an error: add a new migration instead of editing an old one.

## Authorization

`src/Auth/Gate.php` is the single place that maps roles and account states to abilities:

| Ability | Anonymous | Pending | Active member | Administrator |
| --- | --- | --- | --- | --- |
| Read calculators, guides, published community guides, active profiles | ✓ | ✓ | ✓ | ✓ |
| Manage own profile, characters, security, theme | | ✓ | ✓ | ✓ |
| Write drafts, upload images, submit guides | | | ✓ | ✓ |
| View private previews | | own | own | all |
| Moderate guides | | | | ✓ |
| Manage users, settings, audit log, purge guides | | | | ✓ |

A `moderator` role exists in the schema and the gate (guide moderation only) but is not offered in the UI yet. Nothing depends on a username: the owner account is an ordinary `admin`.

## Guide publishing model

See [publishing.md](publishing.md) for the workflow from the member's and administrator's point of view. Technically:

- The public page renders `guide_revisions` row `published_revision_id`, never the working copy, and only when `visibility = 'published'` and the guide is not deleted. Every other state returns the same 404 as a missing page.
- Saving a draft only touches the working copy. Editing a published guide starts an "update draft"; readers keep seeing the published snapshot until an administrator publishes the update.
- `lock_version` provides optimistic locking for autosave, author edits, and administrator edits. Every state transition (submit, withdraw, delete, and every moderation action) also runs in one transaction that locks the guide row (`SELECT … FOR UPDATE`), re-checks that the action is valid for the row as it is now, and updates with `WHERE id = … AND lock_version = …`, requiring exactly one changed row. Moderation forms carry the version the administrator reviewed, so a decision made on a page that has since changed (another administrator acted, or the author withdrew or resubmitted) is refused with a 409 conflict instead of overwriting it. Publishing snapshots the locked row, so the published revision is always the content that was reviewed. Audit records are written in the same transaction; media files of a purged guide are removed only after it commits.
- Account changes that could remove an administrator (status and role changes in the admin area, `user:set-role`, `user:delete`) run through `AccountService::withAdminInvariant()`: a named database lock serialises them, the acting administrator is re-read under lock, the target row is updated conditionally on its previous role and status, and a locking re-count rolls the change back if no active administrator would remain.
- Slugs follow the title until first publication and are fixed afterwards. Existing guide directories, reserved words, and taken slugs are skipped automatically (`-2`, `-3`, …).

## Media

`ImageProcessor` sniffs the real type with `finfo`, rejects SVG and anything that is not JPEG/PNG/WebP/GIF, checks dimensions before decoding (decompression bombs), decodes with GD, resizes onto a fresh canvas, applies EXIF orientation to that small canvas, and re-encodes as WebP (JPEG if WebP is unavailable). Metadata and appended payloads cannot survive. The pixel ceiling is the lower of `media.max_source_pixels`, 8192 px per side, and what PHP's remaining `memory_limit` can decode for that format (a conservative bytes-per-pixel estimate plus the output canvas and headroom), so a large image is refused before decoding rather than exhausting memory on shared hosting. `MediaService` stores files under `storage/media/<2 chars>/<random id>.<ext>` with database ownership. The per-guide count and the per-member quota are checked once cheaply, then again atomically: after decoding (which holds no lock) the guide and member rows are locked, the limits re-counted, and the row inserted in one transaction; a refused upload's file is removed. An image cannot be deleted while the published revision shows it or while a submitted version awaiting review (or approved, awaiting publication) does. `/media/<id>.<ext>` is served by `MediaController` with the stored content type, `nosniff`, and a sandboxing CSP. A guide image is public only while the guide is published and the image is referenced by the **current published revision**; draft images of a published guide, and avatars that are no longer the member's current avatar, are visible only to their owner, the guide's author, and moderators.

YouTube embeds are produced only from a validated 11-character video id and always point at `https://www.youtube-nocookie.com/embed/`.

## Theme system

All colours come from CSS custom properties in `css/styles.css`. Translucent tints use channel tokens such as `rgb(var(--rgb-gold) / 0.28)`, so a theme overrides a handful of channels rather than every rule. Dark is the default `:root`; `:root[data-theme="light"]` provides a parchment-and-ink palette that keeps the gold, ember, and arcane accents. Dark-theme values equal the original hard-coded colours, so the existing appearance is unchanged.

`js/theme.js` runs synchronously in `<head>` before any stylesheet, choosing the signed-in member's saved preference, then the browser's stored choice (`localStorage`), then dark. The toggle in the sidebar is a `button` with `aria-pressed`; for signed-in members the choice is also saved to their account. Dropdown options keep the dark game palette in the light theme so the calculators' colour-coded options stay legible.
